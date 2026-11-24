<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Panneau\Agents\Mcp\McpTool;
use Panneau\Agents\Mcp\PanneauServer;
use Panneau\Agents\Tool;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\Describe;
use Panneau\Agents\Tools\FindItems;
use Panneau\Agents\Tools\GetItem;
use Panneau\Tests\Feature\Http\HttpTestCase;

/**
 * The tools of Panneau served by the MCP server, as the user of the connection.
 */
class McpServerTest extends HttpTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    public function test_the_server_lists_the_tools_with_their_schemas_and_read_only(): void
    {
        $tools = array_column($this->listTools(), null, 'name');

        $this->assertSame(['describe', 'find_items', 'get_item'], array_keys($tools));
        foreach ([Describe::class, FindItems::class, GetItem::class] as $class) {
            $tool = $this->app->make($class);
            $listed = $tools[$tool->name()];
            $this->assertJsonStringEqualsJsonString((string) json_encode($tool->inputSchema()), (string) json_encode($listed['inputSchema']));
            $this->assertSame($tool->description(), $listed['description']);
            $this->assertTrue($listed['annotations']['readOnlyHint']);
            $this->assertFalse($listed['annotations']['destructiveHint']);
        }
    }

    public function test_a_tool_that_writes_needs_a_token_that_writes(): void
    {
        $this->app['config']->set('panneau.agents.tools', [Describe::class, new WritingTool]);
        $this->assertSame(['describe'], array_column($this->listTools(), 'name'));

        $this->actingAs(new TokenUser(['id' => 1], ['mcp:use']));
        $this->assertSame(['describe'], array_column($this->listTools(), 'name'));

        $this->actingAs(new TokenUser(['id' => 1], ['mcp:use', 'panneau:write']));
        $tools = array_column($this->listTools(), null, 'name');
        $this->assertSame(['describe', 'save_note'], array_keys($tools));
        $this->assertFalse($tools['save_note']['annotations']['readOnlyHint']);
        // No properties: an object in JSON, not a list
        $this->assertStringContainsString('"properties":{}', (string) json_encode((new McpTool(new WritingTool))->toArray()));
    }

    public function test_describe(): void
    {
        PanneauServer::tool($this->tool(Describe::class), ['resource' => 'posts', 'parts' => ['list']])
            ->assertOk()
            ->assertStructuredContentPath('id', 'posts');
    }

    public function test_find_items_with_typed_filters(): void
    {
        $this->createPost(['title' => 'Featured', 'featured' => true, 'author_id' => 3]);
        $this->createPost(['title' => 'Other author', 'featured' => true, 'author_id' => 4]);
        $this->createPost(['title' => 'Not featured', 'featured' => false, 'author_id' => 3]);

        // Agents send typed JSON: a boolean and a number, not the strings of a query
        PanneauServer::tool($this->tool(FindItems::class), [
            'resource' => 'posts',
            'filter' => ['featured' => true, 'author_id' => 3],
            'fields' => ['title'],
        ])->assertOk()->assertStructuredContent(function ($json): void {
            $json->where('meta.total', 1)->where('data.0.title', 'Featured')->etc();
        });

        PanneauServer::tool($this->tool(FindItems::class), ['resource' => 'posts', 'filter' => ['featured' => false], 'fields' => ['title']])
            ->assertOk()
            ->assertStructuredContentPath('data.0.title', 'Not featured');
    }

    public function test_get_item(): void
    {
        $post = $this->createPost(['title' => 'Hello']);

        PanneauServer::tool($this->tool(GetItem::class), ['resource' => 'posts', 'id' => (string) $post->getKey()])
            ->assertOk()
            ->assertStructuredContentPath('title', 'Hello');
    }

    public function test_a_problem_is_an_error_with_its_json(): void
    {
        PanneauServer::tool($this->tool(GetItem::class), ['resource' => 'posts', 'id' => '404'])
            ->assertHasErrors(['"status":404']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listTools(): array
    {
        $server = $this->app->make(PanneauServer::class, ['transport' => new FakeTransporter]);
        $server->start();

        return array_values($server->createContext()->tools()->map(fn ($tool): array => json_decode((string) json_encode($tool->toArray()), true))->all());
    }

    /**
     * @param  class-string<Tool>  $class
     */
    private function tool(string $class): McpTool
    {
        return new McpTool($this->app->make($class));
    }
}

/**
 * A user whose token has abilities, like the users of Sanctum and Passport.
 */
class TokenUser extends GenericUser
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $abilities
     */
    public function __construct(array $attributes, private readonly array $abilities)
    {
        parent::__construct($attributes);
    }

    public function tokenCan(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}

/**
 * A tool that writes, which no Panneau tool does yet.
 */
class WritingTool implements Tool
{
    public function name(): string
    {
        return 'save_note';
    }

    public function description(): string
    {
        return 'Saves a note.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function handle(ToolCall $call): ToolResult
    {
        return ToolResult::data(['saved' => true]);
    }
}
