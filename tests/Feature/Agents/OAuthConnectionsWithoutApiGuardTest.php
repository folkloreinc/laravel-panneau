<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Support\Carbon;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Orchestra\Testbench\Attributes\WithMigration;

/**
 * The connections of the account with Passport set up as `docs/architecture.md` says, without a
 * guard `api` (#295): a guard `passport` on `users`, as in a skeleton without `install:api`.
 * Passport's `$user->tokens()` drops the clients of the dynamic registration then, which have no
 * provider.
 */
#[WithMigration]
class OAuthConnectionsWithoutApiGuardTest extends OAuthConnectionsTest
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $guards = $app['config']->get('auth.guards');
        unset($guards['api']);
        $guards['passport'] = ['driver' => 'passport', 'provider' => 'users'];
        $app['config']->set('auth.guards', $guards);
        $app['config']->set('panneau.agents.mcp.guard', 'passport');
        // Another provider of the same table, with its own guard
        $app['config']->set('auth.providers.admins', $app['config']->get('auth.providers.users'));
        $app['config']->set('auth.guards.admins', ['driver' => 'passport', 'provider' => 'admins']);
    }

    public function test_the_tokens_of_a_client_of_another_provider_are_not_connections(): void
    {
        $user = $this->createOAuthUser();
        $client = $this->app->make(ClientRepository::class)->createAuthorizationCodeGrantClient('Admin', [self::REDIRECT]);
        $client->forceFill(['provider' => 'admins'])->save();
        // A token of the admin of the same id
        Passport::token()->newQuery()->forceCreate([
            'id' => str_repeat('a', 80),
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'name' => null,
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => Carbon::now()->addDay(),
        ]);
        $this->actingAs($user, 'web')->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/account/connections')->assertOk()->assertJsonPath('data', []);
        $this->deleteJson('/panneau/api/account/connections/client:'.$client->getKey())->assertNotFound();
        $this->assertSame(0, Passport::token()->newQuery()->where('revoked', true)->count());
    }
}
