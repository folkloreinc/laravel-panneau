<?php

namespace Panneau\Agents\Mcp;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Panneau\Agents\AgentCaller;
use Panneau\Agents\Tool as PanneauTool;
use Panneau\Agents\ToolCall;
use Panneau\Http\Middleware\AuthorizeAgents;

/**
 * A tool of Panneau served by `laravel/mcp`: its name, description and raw JSON Schema, and
 * annotations from `readOnly()`. A call carries the token of the connection as its agent. A
 * result is structured content; a problem is an error with the problem's JSON. Only loaded with
 * `laravel/mcp`.
 */
final class McpTool extends Tool
{
    public function __construct(public readonly PanneauTool $tool) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function title(): string
    {
        return Str::headline($this->tool->name());
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return $this->tool->readOnly()
            ? ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]
            : ['readOnlyHint' => false, 'openWorldHint' => false];
    }

    /**
     * The tool as `tools/list` gives it, with the tool's own JSON Schema: `properties` is an
     * object when there are none.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = $this->tool->inputSchema();
        $schema['type'] ??= 'object';
        if (($schema['properties'] ?? []) === []) {
            $schema['properties'] = (object) [];
        }

        $tool = parent::toArray();
        $tool['inputSchema'] = $schema;

        return $tool;
    }

    /**
     * A tool that writes is left out of a connection whose token can't write.
     */
    public function shouldRegister(Auth $auth): bool
    {
        if ($this->tool->readOnly()) {
            return true;
        }
        // The guard of the token, which the `auth` middleware made the default
        $user = $auth->guard()->user();

        return $user !== null && AuthorizeAgents::tokenCan($user, AuthorizeAgents::WRITE);
    }

    public function handle(Request $request, HttpRequest $http): Response|ResponseFactory
    {
        $user = $request->user() ?? $http->user();
        $result = $this->tool->handle(new ToolCall($user, $request->all(), $http, AgentCaller::fromUser($user)));
        if ($result->problem !== null) {
            return Response::error((string) json_encode($result->problem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $result->data === [] || $result->data === null
            ? Response::json((object) [])
            : Response::structured($result->data);
    }
}
