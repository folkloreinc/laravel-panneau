<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Fortify;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Http\Problem;
use Panneau\Roles\RoleManager;
use Panneau\Support\Facade;
use PragmaRX\Google2FA\Google2FA;
use TestApp\Models\User;

/**
 * Two-factor authentication of the `panneau` mode (panneau-js decision 0049).
 */
#[WithMigration]
class TwoFactorTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('panneau.auth.two_factor', true);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('panneau.roles', [
            'enabled' => true,
            'roles' => [
                'client' => ['label' => 'Client', 'permissions' => [], 'two_factor' => true],
                'editor' => ['label' => 'Editor', 'permissions' => []],
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/fortify/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => true);
    }

    private function user(string $email, bool $twoFactor = false): User
    {
        $user = User::query()->create([
            'name' => ucfirst(strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => Hash::make('secret'),
        ]);
        if ($twoFactor) {
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->app->make(Google2FA::class)->generateSecretKey()),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode(['first-code', 'second-code'])),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    private function code(User $user): string
    {
        return $this->app->make(Google2FA::class)->getCurrentOtp((string) Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
    }

    private function logIn(string $email): void
    {
        $this->postJson('/panneau/api/auth/login', ['email' => $email, 'password' => 'secret'])->assertStatus(202)->assertExactJson(['twoFactor' => true]);
    }

    public function test_the_definition_offers_it(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.twoFactor', true);
    }

    public function test_a_user_without_it_logs_in_with_the_password(): void
    {
        $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'secret'])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_a_login_waits_for_the_code(): void
    {
        $user = $this->user('ada@example.com', twoFactor: true);

        $this->logIn('ada@example.com');
        $this->assertGuest();

        $this->postJson('/panneau/api/auth/two-factor/challenge', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['code']]);
        $this->postJson('/panneau/api/auth/two-factor/challenge', ['code' => $this->code($user)])
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@example.com');
        $this->assertAuthenticatedAs($user);
        // The login confirmed the password
        $this->postJson('/panneau/api/account/two-factor/recovery-codes')->assertOk();
    }

    public function test_a_recovery_code_logs_in_once(): void
    {
        $user = $this->user('ada@example.com', twoFactor: true);

        $this->logIn('ada@example.com');
        $this->postJson('/panneau/api/auth/two-factor/challenge', ['recoveryCode' => 'first-code'])->assertOk();
        $this->assertNotContains('first-code', $user->fresh()?->recoveryCodes() ?? []);

        $this->postJson('/panneau/api/auth/logout')->assertNoContent();
        $this->logIn('ada@example.com');
        $this->postJson('/panneau/api/auth/two-factor/challenge', ['recoveryCode' => 'first-code'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['recoveryCode']]);
    }

    public function test_a_challenge_without_a_login_is_refused(): void
    {
        $this->postJson('/panneau/api/auth/two-factor/challenge', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['code']]);
        $this->postJson('/panneau/api/auth/two-factor/challenge', [])->assertStatus(422);
    }

    public function test_a_user_sets_it_up_and_turns_it_off(): void
    {
        $user = $this->user('ada@example.com');
        $this->actingAs($user)->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/account/two-factor')->assertExactJson(['status' => 'disabled', 'required' => false]);
        $setup = $this->postJson('/panneau/api/account/two-factor')->assertOk();
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('otpauthUrl'));
        $this->assertStringStartsWith('<svg', $setup->json('qrCode'));
        $this->getJson('/panneau/api/account/two-factor')->assertJsonPath('status', 'pending');

        $this->postJson('/panneau/api/account/two-factor/confirm', ['code' => '000000'])->assertStatus(422)->assertJsonStructure(['errors' => ['code']]);
        $code = $this->app->make(Google2FA::class)->getCurrentOtp($setup->json('secret'));
        $codes = $this->postJson('/panneau/api/account/two-factor/confirm', ['code' => $code])->assertOk()->json('recoveryCodes');
        $this->assertCount(8, $codes);
        $this->getJson('/panneau/api/account/two-factor')->assertJsonPath('status', 'enabled');
        $this->postJson('/panneau/api/account/two-factor')->assertStatus(409);
        $this->postJson('/panneau/api/account/two-factor/confirm', ['code' => $code])->assertStatus(409);

        $new = $this->postJson('/panneau/api/account/two-factor/recovery-codes')->assertOk()->json('recoveryCodes');
        $this->assertNotEquals($codes, $new);

        $this->deleteJson('/panneau/api/account/two-factor')->assertNoContent();
        $this->getJson('/panneau/api/account/two-factor')->assertJsonPath('status', 'disabled');
        $this->postJson('/panneau/api/account/two-factor/recovery-codes')->assertStatus(409);
    }

    public function test_the_changes_need_a_recent_password(): void
    {
        $this->user('ada@example.com');
        $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'secret'])->assertOk();
        $this->postJson('/panneau/api/account/two-factor')->assertOk();

        $this->travel(3)->hours();
        $this->postJson('/panneau/api/account/two-factor')
            ->assertStatus(423)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::PASSWORD_CONFIRMATION);
        $this->deleteJson('/panneau/api/account/two-factor')->assertStatus(423);
        // The status doesn't need it
        $this->getJson('/panneau/api/account/two-factor')->assertOk();

        $this->postJson('/panneau/api/auth/password/confirm', ['password' => 'wrong'])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
        $this->postJson('/panneau/api/auth/password/confirm', ['password' => 'secret'])->assertNoContent();
        $this->postJson('/panneau/api/account/two-factor')->assertOk();
    }

    public function test_guests_confirm_no_password(): void
    {
        $this->postJson('/panneau/api/auth/password/confirm', ['password' => 'secret'])->assertUnauthorized();
        $this->getJson('/panneau/api/account/two-factor')->assertUnauthorized();
    }

    public function test_a_user_who_must_have_it_is_refused_until_it_is_set_up(): void
    {
        $this->app['config']->set('panneau.auth.two_factor_required', true);
        $this->withoutVite();
        $user = $this->user('ada@example.com');
        $this->actingAs($user)->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/definition')->assertForbidden()->assertJsonPath('type', Problem::TWO_FACTOR_REQUIRED);
        $html = (string) $this->get('/panneau')->assertOk()->getContent();
        $this->assertStringNotContainsString('"definition"', $html);
        $this->getJson('/panneau/api/account/two-factor')->assertExactJson(['status' => 'disabled', 'required' => true]);

        $setup = $this->postJson('/panneau/api/account/two-factor')->assertOk();
        $this->getJson('/panneau/api/definition')->assertForbidden();
        $this->postJson('/panneau/api/account/two-factor/confirm', ['code' => $this->app->make(Google2FA::class)->getCurrentOtp($setup->json('secret'))])->assertOk();

        $this->getJson('/panneau/api/definition')->assertOk();
        $this->deleteJson('/panneau/api/account/two-factor')->assertStatus(409);
    }

    public function test_an_ability_requires_it(): void
    {
        $this->app['config']->set('panneau.auth.two_factor_required', 'requireTwoFactor');
        Gate::define('requireTwoFactor', fn ($user): bool => $user->email === 'ada@example.com');

        $this->actingAs($this->user('ada@example.com'));
        $this->getJson('/panneau/api/definition')->assertForbidden();
        $this->actingAs($this->user('eve@example.com'));
        $this->getJson('/panneau/api/definition')->assertOk();
    }

    public function test_a_role_requires_it(): void
    {
        $roles = $this->app->make(RoleManager::class);
        $client = $this->user('ada@example.com');
        $roles->syncRoles($client, ['client']);
        $editor = $this->user('eve@example.com');
        $roles->syncRoles($editor, ['editor']);

        $this->actingAs($client);
        $this->getJson('/panneau/api/definition')->assertForbidden()->assertJsonPath('type', Problem::TWO_FACTOR_REQUIRED);
        $this->actingAs($editor);
        $this->getJson('/panneau/api/definition')->assertOk();
    }

    public function test_the_routes_need_the_option(): void
    {
        $this->assertTrue(Route::has('panneau.api.auth.two-factor.challenge'));
        $this->assertTrue(Route::has('panneau.api.account.two-factor'));

        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.auth.two_factor', false);
        Facade::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.auth.two-factor.challenge'));
        $this->assertFalse(Route::has('panneau.api.account.two-factor'));
        $this->assertTrue(Route::has('panneau.api.auth.password.confirm'));
    }
}
