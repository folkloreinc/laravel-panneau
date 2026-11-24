<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\HttpTestCase;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\User;

/**
 * The MCP server over HTTP, with the personal tokens of Sanctum. Concrete tests add
 * `#[WithMigration]` for the `users` table.
 */
abstract class McpTestCase extends HttpTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class, SanctumServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.agents.mcp.enabled', true);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/sanctum/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::mcpRoutes();
        $this->app['router']->getRoutes()->refreshNameLookups();
        // Clients come with a token, not the session of HttpTestCase
        $this->app['auth']->forgetGuards();
    }

    protected function createUser(string $email = 'ada@example.com', ?int $id = null): User
    {
        return User::query()->create(array_filter([
            'id' => $id,
            'name' => ucfirst(strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => 'secret',
        ]));
    }

    /**
     * A JSON-RPC request to the MCP server, with a bearer token.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    protected function rpc(?string $token, string $method, array $params = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = $token !== null ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->postJson('/panneau/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }
}
