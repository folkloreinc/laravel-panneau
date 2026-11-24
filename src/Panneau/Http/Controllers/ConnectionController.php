<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;
use Panneau\Agents\Connection;
use Panneau\Agents\Connections;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The connections of the account to the MCP server (decision 0029), with
 * `panneau.agents.mcp.enabled`: `GET /account/connections`, `POST /account/connections/tokens`
 * (a personal token, whose plain text is answered once, or a `409` problem when none can be
 * made: `agents.mcp.tokens` of the definition is false) and `DELETE /account/connections/{id}`.
 * The user passes the `usePanneauAgents` gate when the project defines it.
 *
 * @internal
 */
final class ConnectionController
{
    public function __construct(
        private readonly Connections $connections,
        private readonly Gate $gate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return new JsonResponse(['data' => array_map(fn (Connection $connection): array => $connection->toArray(), $this->connections->all($user))]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'write' => ['sometimes', 'boolean'],
        ]);
        if (! $this->connections->canCreateTokens($user)) {
            // Passport without a personal access client, for example: the definition says so
            throw new ConflictHttpException(__('This account can\'t make personal tokens.'));
        }
        $token = $this->connections->createToken(
            $user,
            $input['name'],
            (bool) ($input['write'] ?? false),
            $this->connections->expiry(),
        );

        return new JsonResponse(['token' => $token->plainTextToken, 'connection' => $token->connection->toArray()], 201);
    }

    public function destroy(Request $request, string $connection): Response
    {
        if (! $this->connections->revoke($this->user($request), $connection)) {
            throw new NotFoundHttpException;
        }

        return new Response(status: 204);
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            throw new LogicException('The account routes need the user of the panneau.auth middleware.');
        }
        $gate = $this->gate->forUser($user);
        if ($gate->has('usePanneauAgents') && $gate->denies('usePanneauAgents')) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
