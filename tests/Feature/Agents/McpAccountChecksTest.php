<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Http\Problem;
use TestApp\Models\User;

/**
 * The checks of `panneau.auth` on the user, repeated for a token: the email verification and the
 * requirement of two-factor authentication (#282).
 */
#[WithMigration]
class McpAccountChecksTest extends McpTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('panneau.auth.mode', 'panneau');
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/fortify/database/migrations');
    }

    private function token(User $user): string
    {
        return $user->createToken('agent', ['mcp:use'])->plainTextToken;
    }

    public function test_a_user_whose_address_is_not_verified_is_refused(): void
    {
        $this->app['config']->set('panneau.auth.email_verification', true);
        $unverified = $this->token($this->createUser());
        $verified = $this->createUser('eve@example.com');
        $verified->forceFill(['email_verified_at' => now()])->save();

        $this->rpc($unverified, 'tools/list')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::EMAIL_UNVERIFIED);
        $this->rpc($this->token($verified), 'tools/list')->assertOk();

        $this->app['config']->set('panneau.auth.email_verification', false);
        $this->rpc($unverified, 'tools/list')->assertOk();
    }

    public function test_a_user_who_must_set_up_two_factor_authentication_is_refused(): void
    {
        $this->app['config']->set('panneau.auth.two_factor', true);
        $this->app['config']->set('panneau.auth.two_factor_required', true);
        $without = $this->token($this->createUser());
        $with = $this->createUser('eve@example.com');
        $with->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();

        $this->rpc($without, 'tools/list')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::TWO_FACTOR_REQUIRED);
        $this->rpc($this->token($with), 'tools/list')->assertOk();

        $this->app['config']->set('panneau.auth.two_factor_required', false);
        $this->rpc($without, 'tools/list')->assertOk();
    }

    public function test_the_command_refuses_a_user_whose_address_is_not_verified(): void
    {
        $this->app['config']->set('panneau.auth.email_verification', true);
        $this->createUser();

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain('The email address of "ada@example.com" is not verified')
            ->assertFailed();
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->createUser('eve@example.com')->forceFill(['email_verified_at' => now()])->save();
        $this->assertSame(0, Artisan::call('panneau:agent-token', ['user' => 'eve@example.com']));
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_the_command_refuses_a_user_who_must_set_up_two_factor_authentication(): void
    {
        $this->app['config']->set('panneau.auth.two_factor', true);
        $this->app['config']->set('panneau.auth.two_factor_required', true);
        $this->createUser();

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain('"ada@example.com" must set up two-factor authentication')
            ->assertFailed();
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->createUser('eve@example.com')->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();
        $this->assertSame(0, Artisan::call('panneau:agent-token', ['user' => 'eve@example.com']));
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }
}
