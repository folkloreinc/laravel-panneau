<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys as LaravelPasskeys;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Auth\Passkeys;
use Panneau\Http\Problem;
use TestApp\Models\User;

/**
 * Passkeys of the `panneau` mode (panneau-js decision 0051). The checks of the credentials are
 * those of laravel/passkeys: here, a double of `Panneau\Auth\Passkeys` answers for them.
 */
#[WithMigration]
class PasskeyTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('panneau.auth.passkeys', true);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passkeys', [
            'relying_party_id' => 'localhost',
            'allowed_origins' => ['http://localhost'],
            'user_handle_secret' => 'secret',
            'timeout' => 60000,
        ]);
        LaravelPasskeys::useUserModel(User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/passkeys/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => $user->email !== 'eve@example.com');
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => 'Ada', 'email' => $email, 'password' => Hash::make('secret')]);
    }

    private function passkey(User $user, string $name = 'Laptop'): Passkey
    {
        // The id of a credential is in base64url
        $id = rtrim(strtr(base64_encode($name.'-id'), '+/', '-_'), '=');

        return $user->passkeys()->create(['name' => $name, 'credential_id' => $id, 'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000']]);
    }

    /**
     * The double of the ceremonies: every credential is the given passkey.
     */
    private function verifying(Passkey $passkey): void
    {
        $this->app->instance(Passkeys::class, new class($passkey) extends Passkeys
        {
            public function __construct(private readonly Passkey $passkey)
            {
                parent::__construct(app());
            }

            public function verify(array $credential, string $options): Passkey
            {
                return $this->passkey;
            }

            public function store(PasskeyUser $user, string $name, array $credential, string $options): Passkey
            {
                return $user->passkeys()->create(['name' => $name, 'credential_id' => 'bmV3LWlk', 'credential' => []]);
            }
        });
    }

    public function test_the_definition_has_them(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.passkeys', true);
    }

    public function test_a_login_gets_options_for_any_passkey(): void
    {
        $options = $this->postJson('/panneau/api/auth/passkeys/options')->assertOk()->json('options');

        $this->assertIsString($options['challenge']);
        $this->assertSame('localhost', $options['rpId']);
        $this->assertSame('required', $options['userVerification']);
        $this->assertIsString(session('panneau.auth.passkey_login_options'));
    }

    public function test_a_user_logs_in_with_a_passkey_without_a_second_factor(): void
    {
        $user = $this->user('ada@example.com');
        $this->verifying($this->passkey($user));

        $this->postJson('/panneau/api/auth/passkeys/options')->assertOk();
        $this->postJson('/panneau/api/auth/passkeys/login', ['credential' => ['id' => 'Laptop-id']])
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@example.com');

        $this->assertAuthenticatedAs($user);
        // The options are used once
        $this->postJson('/panneau/api/auth/passkeys/login', ['credential' => ['id' => 'Laptop-id']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credential']]);
    }

    public function test_users_the_gate_refuses_are_not_logged_in(): void
    {
        $this->verifying($this->passkey($this->user('eve@example.com')));

        $this->postJson('/panneau/api/auth/passkeys/options');
        $this->postJson('/panneau/api/auth/passkeys/login', ['credential' => ['id' => 'Laptop-id']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credential']]);
        $this->assertGuest();
    }

    public function test_a_credential_that_cant_be_read_is_refused(): void
    {
        $this->postJson('/panneau/api/auth/passkeys/options');

        $this->postJson('/panneau/api/auth/passkeys/login', ['credential' => ['id' => 'nope']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credential']]);
        $this->postJson('/panneau/api/auth/passkeys/login', [])->assertStatus(422);
    }

    public function test_the_account_lists_adds_and_removes_passkeys(): void
    {
        $user = $this->user('ada@example.com');
        $passkey = $this->passkey($user);
        $this->verifying($passkey);
        $this->actingAs($user)->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/account/passkeys')->assertOk()->assertJsonPath('data.0', [
            'id' => (string) $passkey->getKey(),
            'name' => 'Laptop',
            'authenticator' => null,
            'createdAt' => $passkey->created_at?->toIso8601String(),
            'lastUsedAt' => null,
        ]);

        $options = $this->postJson('/panneau/api/account/passkeys/options')->assertOk()->json('options');
        $this->assertSame('ada@example.com', $options['user']['name']);
        $this->postJson('/panneau/api/account/passkeys', ['name' => 'Phone', 'credential' => ['id' => 'x']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Phone');
        $this->assertCount(2, $user->passkeys()->get());

        $this->deleteJson('/panneau/api/account/passkeys/'.$passkey->getKey())->assertNoContent();
        $this->assertCount(1, $user->passkeys()->get());
    }

    public function test_a_registration_needs_its_options(): void
    {
        $this->actingAs($this->user('ada@example.com'))->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->postJson('/panneau/api/account/passkeys', ['name' => 'Phone', 'credential' => ['id' => 'x']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credential']]);
    }

    public function test_the_changes_need_a_recent_password(): void
    {
        $user = $this->user('ada@example.com');
        $passkey = $this->passkey($user);
        $this->actingAs($user);

        $this->postJson('/panneau/api/account/passkeys/options')->assertStatus(423)->assertJsonPath('type', Problem::PASSWORD_CONFIRMATION);
        $this->deleteJson('/panneau/api/account/passkeys/'.$passkey->getKey())->assertStatus(423);
        $this->getJson('/panneau/api/account/passkeys')->assertOk();
    }

    public function test_the_passkeys_of_others_are_not_found(): void
    {
        $other = $this->passkey($this->user('grace@example.com'));
        $this->actingAs($this->user('ada@example.com'))->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->deleteJson('/panneau/api/account/passkeys/'.$other->getKey())->assertNotFound();
        $this->assertNotNull($other->fresh());
    }

    public function test_users_without_passkeys_have_an_empty_list(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']))->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/account/passkeys')->assertExactJson(['data' => []]);
        $this->postJson('/panneau/api/account/passkeys/options')->assertStatus(409);
    }
}
