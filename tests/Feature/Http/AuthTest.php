<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;

/**
 * The auth routes of the `panneau` mode, with the users of the skeleton's `users` table.
 */
#[WithMigration]
class AuthTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => $user->getAuthIdentifier() !== $this->user('eve@example.com')->getKey());
    }

    private function user(string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            return $user;
        }
        $user = new User;
        $user->forceFill(['name' => ucfirst(strstr($email, '@', true) ?: $email), 'email' => $email, 'password' => Hash::make('secret')])->save();

        return $user;
    }

    public function test_a_user_logs_in(): void
    {
        $user = $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'secret'])
            ->assertOk()
            ->assertExactJson(['user' => ['id' => (string) $user->getKey(), 'name' => 'Ada', 'email' => 'ada@example.com', 'abilities' => ['panneau.view' => true]]]);

        $this->assertAuthenticatedAs($user);
        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('user.id', (string) $user->getKey());
    }

    public function test_a_login_can_be_remembered(): void
    {
        $this->user('ada@example.com');

        $response = $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'secret', 'remember' => true])->assertOk();

        $this->assertNotNull($response->getCookie($this->app['auth']->guard()->getRecallerName()));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('errors.email.0', trans('auth.failed'));

        $this->assertGuest();
    }

    public function test_users_the_gate_refuses_are_not_logged_in(): void
    {
        $this->user('eve@example.com');

        $this->postJson('/panneau/api/auth/login', ['email' => 'eve@example.com', 'password' => 'secret'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', trans('auth.failed'));

        $this->assertGuest();
    }

    public function test_the_credentials_are_validated(): void
    {
        $this->postJson('/panneau/api/auth/login', ['token' => 'secret'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_logins_are_throttled(): void
    {
        $this->user('ada@example.com');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/panneau/api/auth/login', ['email' => 'ADA@example.com', 'password' => 'secret'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn (string $message): bool => $message !== trans('auth.failed') && str_contains($message, 'seconds'));

        $this->assertGuest();
    }

    public function test_a_user_logs_out(): void
    {
        $user = $this->user('ada@example.com');
        $this->actingAs($user);
        $this->withSession(['panneau.test' => true]);

        $this->postJson('/panneau/api/auth/logout')->assertNoContent();

        $this->assertGuest();
        $this->assertNull(session('panneau.test'));
        $this->getJson('/panneau/api/definition')->assertStatus(401);
    }

    public function test_users_the_gate_refuses_can_log_out(): void
    {
        $this->actingAs($this->user('eve@example.com'));
        $this->getJson('/panneau/api/definition')->assertForbidden();

        $this->postJson('/panneau/api/auth/logout')->assertNoContent();

        $this->assertGuest();
    }

    public function test_guests_can_log_out(): void
    {
        $this->postJson('/panneau/api/auth/logout')->assertNoContent();
    }

    public function test_the_auth_routes_are_only_in_the_panneau_mode(): void
    {
        $this->assertTrue(Route::has('panneau.api.auth.login'));
        $this->assertTrue(Route::has('panneau.api.auth.logout'));

        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.auth.mode', 'external');
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.auth.login'));
        $this->assertFalse(Route::has('panneau.api.auth.logout'));
    }
}
