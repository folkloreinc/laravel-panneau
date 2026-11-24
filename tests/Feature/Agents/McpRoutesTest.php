<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Events\AgentToolCalled;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Post;

/**
 * The routes of the MCP server: a bearer token with `mcp:use`, the gates, the rate limiter.
 */
#[WithMigration]
class McpRoutesTest extends McpTestCase
{
    public function test_a_token_with_mcp_use_lists_and_calls_the_tools(): void
    {
        $token = $this->createUser()->createToken('agent', ['mcp:use'])->plainTextToken;
        $this->createPost(['title' => 'Featured', 'featured' => true]);

        $this->rpc($token, 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item'])
            ->assertJsonPath('result.tools.0.annotations.readOnlyHint', true);

        $this->rpc($token, 'tools/call', ['name' => 'find_items', 'arguments' => ['resource' => 'posts', 'filter' => ['featured' => true]]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.0.title', 'Featured');
    }

    public function test_a_token_that_writes_saves_items_as_its_agent(): void
    {
        Event::fake([AgentToolCalled::class]);
        $token = $this->createUser()->createToken('assistant', ['mcp:use', 'panneau:write']);

        $this->rpc($token->plainTextToken, 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item', 'save_item', 'run_action'])
            ->assertJsonPath('result.tools.3.annotations.readOnlyHint', false);

        $values = ['title' => 'By an agent', 'status' => 'draft', 'preview_password' => 'a long secret'];
        $this->rpc($token->plainTextToken, 'tools/call', ['name' => 'save_item', 'arguments' => ['resource' => 'posts', 'values' => $values]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.title', 'By an agent');

        $this->assertSame(1, Post::query()->where('title', 'By an agent')->count());
        Event::assertDispatched(AgentToolCalled::class, fn (AgentToolCalled $event): bool => $event->agent?->id === (string) $token->accessToken->getKey()
            && $event->agent->name === 'assistant');
    }

    public function test_a_token_that_reads_can_not_call_save_item(): void
    {
        $token = $this->createUser()->createToken('agent', ['mcp:use'])->plainTextToken;

        // Not registered for the token: laravel/mcp answers that the tool doesn't exist
        $this->rpc($token, 'tools/call', ['name' => 'save_item', 'arguments' => ['resource' => 'posts', 'values' => ['title' => 'Refused', 'status' => 'draft', 'preview_password' => 'a long secret']]])
            ->assertStatus(400)
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', 'Tool [save_item] not found.');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_without_a_token_it_is_unauthorized(): void
    {
        $this->rpc(null, 'tools/list')->assertStatus(401)->assertJsonPath('status', 401);
        $this->rpc('1|wrong', 'tools/list')->assertStatus(401);
    }

    public function test_a_token_without_mcp_use_is_forbidden(): void
    {
        $token = $this->createUser()->createToken('other', ['reports'])->plainTextToken;

        $this->rpc($token, 'tools/list')->assertStatus(403)->assertJsonPath('status', 403);
    }

    public function test_a_user_the_gates_refuse_is_forbidden(): void
    {
        // HttpTestCase's viewPanneau refuses the user 99
        $refused = $this->createUser('eve@example.com', 99)->createToken('agent', ['mcp:use'])->plainTextToken;
        $this->rpc($refused, 'tools/list')->assertStatus(403);

        $token = $this->createUser()->createToken('agent', ['mcp:use'])->plainTextToken;
        Gate::define('usePanneauAgents', fn (): bool => false);
        $this->rpc($token, 'tools/list')->assertStatus(403);
        Gate::define('usePanneauAgents', fn (): bool => true);
        $this->rpc($token, 'tools/list')->assertOk();
    }

    public function test_the_rate_limiter_answers_429(): void
    {
        $this->app['config']->set('panneau.agents.rate_limit', 2);
        $token = $this->createUser()->createToken('agent', ['mcp:use'])->plainTextToken;

        $this->rpc($token, 'tools/list')->assertOk();
        $this->rpc($token, 'tools/list')->assertOk();
        $this->rpc($token, 'tools/list')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_disabled_it_registers_nothing(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.agents.mcp.enabled', false);

        Panneau::mcpRoutes();

        $this->assertCount(0, $this->app['router']->getRoutes());
    }

    public function test_the_route_is_under_the_path_of_panneau(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('panneau.mcp');

        $this->assertNotNull($route);
        $this->assertSame('panneau/mcp/{panneauContext?}', $route->uri());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    public function test_a_session_without_a_token_is_unauthorized(): void
    {
        // A real session, in the database, so that only a request that starts it sees the user
        $this->app['config']->set('session.driver', 'database');
        $this->forgetSession();
        $user = $this->createUser();
        Route::middleware('web')->post('/test-login', fn () => Auth::guard('web')->login($user));
        Route::middleware('web')->get('/test-whoami', fn (): string => (string) Auth::guard('web')->id());
        $this->post('/test-login')->assertOk();
        $session = $this->app['session']->driver()->getId();
        $this->forgetSession();
        $this->withCookie((string) $this->app['config']->get('session.cookie'), $session);

        // The cookie logs the user in where the session starts, but not on the MCP server
        $this->assertSame((string) $user->getKey(), $this->get('/test-whoami')->getContent());
        $this->forgetSession();
        $this->rpc(null, 'tools/list')->assertStatus(401);
    }

    /**
     * Forgets the session of the last request, as a new process would: only a request that starts
     * the session reads it from its cookie.
     */
    private function forgetSession(): void
    {
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
    }
}
