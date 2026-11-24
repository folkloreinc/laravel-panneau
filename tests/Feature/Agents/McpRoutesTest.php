<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Support\Facade as Panneau;

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
        $this->assertSame('panneau/mcp', $route->uri());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }
}
