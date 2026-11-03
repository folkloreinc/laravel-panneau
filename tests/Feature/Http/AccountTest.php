<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Auth\Notifications\VerifyEmail;
use TestApp\Models\User;

/**
 * The account of the current user in the `panneau` mode (panneau-js decision 0050).
 */
#[WithMigration]
class AccountTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => true);
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => ucfirst(strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => Hash::make('secret'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_the_definition_has_the_account_page(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.account', true);
    }

    public function test_it_gives_the_current_user(): void
    {
        $user = $this->user('ada@example.com');
        $this->actingAs($user);

        $this->getJson('/panneau/api/account')->assertOk()->assertJsonPath('data', [
            'id' => (string) $user->getKey(),
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'abilities' => ['panneau.view' => true],
        ]);
    }

    public function test_guests_have_no_account(): void
    {
        $this->getJson('/panneau/api/account')->assertUnauthorized();
        $this->putJson('/panneau/api/account/password', ['currentPassword' => 'secret', 'password' => 'a new password'])->assertUnauthorized();
    }

    public function test_it_saves_the_name_and_the_email(): void
    {
        $user = $this->user('ada@example.com');
        $this->user('eve@example.com');
        $this->actingAs($user);

        $this->patchJson('/panneau/api/account', ['name' => 'Ada', 'email' => 'eve@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
        $this->patchJson('/panneau/api/account', ['name' => '', 'email' => 'ada@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);

        $this->patchJson('/panneau/api/account', ['name' => 'Ada Lovelace', 'email' => 'ada@example.org'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.org');
        // Without the email verification, the address stays verified
        $this->assertTrue($user->fresh()?->hasVerifiedEmail());
    }

    public function test_a_new_email_is_verified_again(): void
    {
        Notification::fake();
        $this->app['config']->set('panneau.auth.email_verification', true);
        $user = $this->user('ada@example.com');
        $this->actingAs($user);

        $this->patchJson('/panneau/api/account', ['name' => 'Ada', 'email' => 'ada@example.org'])->assertOk();

        $this->assertFalse($user->fresh()?->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->getJson('/panneau/api/definition')->assertForbidden();
    }

    public function test_the_action_of_the_config_saves_the_account(): void
    {
        $this->app['config']->set('panneau.auth.update_profile_using', RecordingProfileAction::class);
        $this->actingAs($this->user('ada@example.com'));

        $this->patchJson('/panneau/api/account', ['name' => 'Ada L.', 'email' => 'ada@example.com'])->assertOk();

        $this->assertSame(['name' => 'Ada L.', 'email' => 'ada@example.com'], RecordingProfileAction::$input);
    }

    public function test_it_changes_the_password_with_the_current_one(): void
    {
        $user = $this->user('ada@example.com');
        $this->actingAs($user);

        $this->putJson('/panneau/api/account/password', ['currentPassword' => 'wrong', 'password' => 'a new password'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['currentPassword']])
            ->assertJsonMissingPath('errors.current_password');
        $this->putJson('/panneau/api/account/password', ['currentPassword' => 'secret', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->putJson('/panneau/api/account/password', ['currentPassword' => 'secret', 'password' => 'a new password'])->assertNoContent();
        $this->assertTrue(Hash::check('a new password', (string) $user->fresh()?->password));
    }
}

class RecordingProfileAction implements UpdatesUserProfileInformation
{
    /**
     * @var array<string, mixed>|null
     */
    public static ?array $input = null;

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        self::$input = $input;
    }
}
