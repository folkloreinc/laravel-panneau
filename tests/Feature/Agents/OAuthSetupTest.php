<?php

namespace Panneau\Tests\Feature\Agents;

use LogicException;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\User;

/**
 * The pieces of the OAuth setup that `Panneau::mcpRoutes()` checks (#300): each missing one is a
 * `LogicException` that names it, instead of a login that never comes back or a `401`.
 */
#[WithMigration]
class OAuthSetupTest extends OAuthTestCase
{
    private function assertRefused(string $message): void
    {
        try {
            Panneau::mcpRoutes();
        } catch (LogicException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }
        $this->fail('Panneau::mcpRoutes() registered OAuth with a missing piece.');
    }

    public function test_the_guard_of_the_server_must_be_a_passport_guard(): void
    {
        config(['panneau.agents.mcp.guard' => 'sanctum']);
        $this->assertRefused('needs a passport guard in panneau.agents.mcp.guard: "sanctum" has the "sanctum" driver');

        config(['panneau.agents.mcp.guard' => 'missing']);
        $this->assertRefused('"missing" is not a guard of config/auth.php');
    }

    public function test_the_users_must_be_a_model_of_passport(): void
    {
        config(['auth.providers.users.model' => User::class]);

        $this->assertRefused(User::class.' must implement Laravel\Passport\Contracts\OAuthenticatable');
    }

    public function test_passport_must_see_the_users_of_panneau_logged_in(): void
    {
        config(['auth.guards.admins' => ['driver' => 'session', 'provider' => 'users'], 'panneau.auth.guard' => 'admins']);

        $this->assertRefused('passport.guard is "web", but the users of Panneau log in on "admins": set passport.guard to "admins"');
    }

    public function test_a_complete_setup_registers_the_routes(): void
    {
        // Both on the default guard
        config(['passport.guard' => null, 'panneau.auth.guard' => null]);

        Panneau::mcpRoutes();

        $this->assertTrue($this->app['router']->has('mcp.oauth.register'));
    }
}
