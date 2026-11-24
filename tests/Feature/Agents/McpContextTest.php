<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Agents\AgentCaller;
use Panneau\Agents\Confirmation;
use Panneau\Context\Context;
use Panneau\Context\ContextOption;
use Panneau\Context\ContextParam;
use Panneau\Support\Facade as Panneau;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\Author;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\ContextPostsResource;

/**
 * The MCP server of a Panneau with a context: the URL of a connection is followed by the prefix
 * of the context, as in the app, checked for the user as the `Panneau-Context` header is.
 */
#[WithMigration]
class McpContextTest extends McpTestCase
{
    private Author $ada;

    private Author $grace;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([ContextPostsResource::class]);
        $this->ada = Author::query()->create(['name' => 'Ada Lovelace']);
        $this->grace = Author::query()->create(['name' => 'Grace Hopper']);
        // Authors named "Hidden" are not for the user
        Gate::before(fn (?Authenticatable $user, string $ability, array $arguments): ?bool => $ability === 'view' && ($arguments[0] ?? null) instanceof Author && $arguments[0]->name === 'Hidden' ? false : null);
        $this->token = $this->createUser()->createToken('agent', ['mcp:use'])->plainTextToken;
    }

    private function declareContext(?ContextParam $author = null): void
    {
        Panneau::context(fn (): array => [
            $author ?? ContextParam::make('author')->label('Author')->resource(AuthorsResource::class)->required(),
            ContextParam::make('season')->options(['2025' => 'Season 2025', '2026' => 'Season 2026']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function callTool(string $tool, array $arguments, string $context = '', array $headers = []): TestResponse
    {
        return $this->rpc($this->token, 'tools/call', ['name' => $tool, 'arguments' => $arguments], $context, $headers);
    }

    /**
     * The problem of a tool's result.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private static function problem(TestResponse $response): array
    {
        $response->assertOk()->assertJsonPath('result.isError', true);

        return (array) json_decode((string) $response->json('result.content.0.text'), true);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private static function assertContextProblem(TestResponse $response, string $param): void
    {
        $response->assertForbidden()
            ->assertJsonPath('type', 'urn:panneau:problem:context')
            ->assertJsonPath('param', $param);
    }

    public function test_the_prefix_scopes_the_tools_to_its_values(): void
    {
        $this->declareContext();
        $mine = $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);
        $other = $this->createPost(['title' => 'On compilers', 'author_id' => $this->grace->id]);
        $ada = 'author/'.$this->ada->id;

        $this->callTool('find_items', ['resource' => 'context-posts'], $ada)
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.*.title', ['Notes on the engine']);
        $this->callTool('get_item', ['resource' => 'context-posts', 'id' => (string) $mine->id], $ada)
            ->assertJsonPath('result.structuredContent.title', 'Notes on the engine');
        $this->assertSame(404, self::problem($this->callTool('get_item', ['resource' => 'context-posts', 'id' => (string) $other->id], $ada))['status']);

        // At its own value, with a second param
        $this->callTool('get_item', ['resource' => 'context-posts', 'id' => (string) $other->id], 'author/'.$this->grace->id.'/season/2026')
            ->assertJsonPath('result.structuredContent.title', 'On compilers');
    }

    public function test_the_header_is_ignored(): void
    {
        $this->declareContext();
        $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);
        $this->createPost(['title' => 'On compilers', 'author_id' => $this->grace->id]);

        $this->callTool('find_items', ['resource' => 'context-posts'], 'author/'.$this->ada->id, ['Panneau-Context' => 'author='.$this->grace->id])
            ->assertJsonPath('result.structuredContent.data.*.title', ['Notes on the engine']);
        // Not even a value the user can't use
        $hidden = Author::query()->create(['name' => 'Hidden']);
        $this->callTool('find_items', ['resource' => 'context-posts'], 'author/'.$this->ada->id, ['Panneau-Context' => 'author='.$hidden->id])
            ->assertJsonPath('result.isError', false);
    }

    public function test_without_a_prefix_the_defaults_apply(): void
    {
        $this->declareContext(ContextParam::make('author')->resource(AuthorsResource::class)->required()->default(fn (): string => (string) $this->grace->id));
        $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);
        $this->createPost(['title' => 'On compilers', 'author_id' => $this->grace->id]);

        $this->callTool('find_items', ['resource' => 'context-posts'])
            ->assertJsonPath('result.structuredContent.data.*.title', ['On compilers']);
    }

    public function test_without_a_value_a_resource_that_needs_one_is_a_context_problem(): void
    {
        $this->declareContext();

        $problem = self::problem($this->callTool('find_items', ['resource' => 'context-posts']));

        $this->assertSame(['urn:panneau:problem:context', 403, 'author'], [$problem['type'], $problem['status'], $problem['param']]);
    }

    public function test_a_value_the_user_can_not_use_is_a_context_problem(): void
    {
        $this->declareContext();
        $hidden = Author::query()->create(['name' => 'Hidden']);

        self::assertContextProblem($this->callTool('find_items', ['resource' => 'context-posts'], 'author/'.$hidden->id), 'author');
        self::assertContextProblem($this->callTool('find_items', ['resource' => 'context-posts'], 'author/999'), 'author');
        self::assertContextProblem($this->callTool('describe', [], 'author/'.$this->ada->id.'/season/1999'), 'season');
    }

    public function test_a_fixed_value_that_differs_from_the_prefix_is_refused(): void
    {
        $grace = (string) $this->grace->id;
        $this->declareContext(
            ContextParam::make('author')->resource(AuthorsResource::class)->required()
                ->fixed(fn (Request $request): ?string => $request->getHost() === 'grace.test' ? $grace : null),
        );
        $this->createPost(['title' => 'On compilers', 'author_id' => $this->grace->id]);

        $this->app['auth']->forgetGuards();
        $rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'find_items', 'arguments' => ['resource' => 'context-posts']]];
        $headers = ['Authorization' => 'Bearer '.$this->token];
        self::assertContextProblem($this->postJson('http://grace.test/panneau/mcp/author/'.$this->ada->id, $rpc, $headers), 'author');
        $this->app['auth']->forgetGuards();
        $this->postJson('http://grace.test/panneau/mcp/author/'.$grace, $rpc, $headers)
            ->assertJsonPath('result.structuredContent.data.*.title', ['On compilers']);
        $this->app['auth']->forgetGuards();
        $this->postJson('http://grace.test/panneau/mcp', $rpc, $headers)
            ->assertJsonPath('result.structuredContent.data.*.title', ['On compilers']);
    }

    public function test_an_unknown_param_or_a_malformed_prefix_is_not_found(): void
    {
        $this->declareContext();
        $ada = (string) $this->ada->id;

        foreach (['client/'.$ada, 'author', 'author/'.$ada.'/season', 'season/2026/author/'.$ada, 'author/'.$ada.'/author/'.$ada, 'author//'.$ada] as $prefix) {
            $this->rpc($this->token, 'tools/list', [], $prefix)->assertNotFound();
        }
        $this->rpc($this->token, 'tools/list', [], 'author/'.$ada)->assertOk();
    }

    public function test_the_get_and_delete_routes_follow_the_prefix(): void
    {
        $this->declareContext();
        $methods = [];
        foreach ($this->app['router']->getRoutes() as $route) {
            if ($route->uri() === 'panneau/mcp/{panneauContext?}') {
                $methods = [...$methods, ...$route->methods()];
            }
        }

        $this->assertEqualsCanonicalizing(['GET', 'HEAD', 'DELETE', 'POST'], $methods);
        // The app's own route has GET
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/panneau/mcp/author/'.$this->ada->id, [], ['Authorization' => 'Bearer '.$this->token])->assertStatus(405);
    }

    public function test_describe_and_the_instructions_give_the_context_of_the_connection(): void
    {
        $this->declareContext();

        $context = $this->callTool('describe', [], 'author/'.$this->ada->id.'/season/2026')->json('result.structuredContent.context.params');
        $this->assertSame(
            [['author', (string) $this->ada->id, 'Ada Lovelace'], ['season', '2026', 'Season 2026']],
            array_map(fn (array $param): array => [$param['name'], $param['value'], $param['valueLabel']], (array) $context),
        );

        $this->rpc($this->token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']], 'author/'.$this->ada->id)
            ->assertOk()
            ->assertSee('This connection is made for the context author \"Ada Lovelace\"', false);
    }

    public function test_a_confirmation_is_bound_to_the_context(): void
    {
        $confirmation = $this->app->make(Confirmation::class);
        $context = $this->app->make(Context::class);
        $agent = new AgentCaller('7', 'Assistant');
        $user = $this->createUser('grace@example.com');
        $arguments = [$user, $agent, 'context-posts', 'delete', ['ids' => ['1']], []];

        $context->set('author', new ContextOption((string) $this->ada->id, 'Ada Lovelace'));
        $token = $confirmation->sign(...$arguments);
        $this->assertTrue($confirmation->verify($token, ...$arguments));

        $context->replace(new Context);
        $context->set('author', new ContextOption((string) $this->grace->id, 'Grace Hopper'));
        $this->assertFalse($confirmation->verify($token, ...$arguments));
        $context->replace(new Context);
        $this->assertFalse($confirmation->verify($token, ...$arguments));
    }
}
