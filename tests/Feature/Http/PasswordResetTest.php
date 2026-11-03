<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Auth\Notifications\ResetPassword;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\User;

/**
 * The password reset of the `panneau` mode (panneau-js decision 0048).
 */
#[WithMigration]
class PasswordResetTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('panneau.auth.password_reset', true);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => $user->email !== 'eve@example.com');
    }

    protected function tearDown(): void
    {
        BaseResetPassword::$toMailCallback = null;
        ResetPassword::$toMailCallback = null;

        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => ucfirst(strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => Hash::make('secret'),
        ]);
    }

    public function test_the_definition_offers_the_reset(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.passwordReset', true);
    }

    public function test_a_link_is_sent_to_the_page_of_the_app(): void
    {
        Notification::fake();
        $user = $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'ada@example.com'])->assertNoContent();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->assertSame('http://localhost/panneau/auth/reset-password?token='.rawurlencode($notification->token).'&email=ada%40example.com', $notification->url);
            $this->assertTrue(Password::broker()->tokenExists($user, $notification->token));

            return true;
        });
        Notification::assertNotSentTo($user, BaseResetPassword::class, fn ($notification): bool => ! $notification instanceof ResetPassword);
    }

    public function test_the_email_has_its_own_callbacks(): void
    {
        // The site's own reset email
        BaseResetPassword::toMailUsing(fn (): MailMessage => (new MailMessage)->line('The site'));
        $notification = new ResetPassword('token', 'https://example.com/panneau/auth/reset-password?token=token');

        $mail = $notification->toMail($this->user('ada@example.com'));

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('https://example.com/panneau/auth/reset-password?token=token', $mail->actionUrl);
    }

    public function test_the_answer_does_not_tell_which_addresses_have_an_account(): void
    {
        Notification::fake();
        $eve = $this->user('eve@example.com');

        $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'nobody@example.com'])->assertNoContent();
        // A user the gate refuses gets no link
        $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'eve@example.com'])->assertNoContent();

        Notification::assertNothingSent();
        $this->assertNotNull($eve->fresh());
    }

    public function test_the_email_is_validated(): void
    {
        $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'not an email'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_requests_are_throttled(): void
    {
        Notification::fake();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'nobody@example.com'])->assertNoContent();
        }

        $this->postJson('/panneau/api/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_a_password_is_reset_without_logging_in(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->user('ada@example.com');
        $token = Password::broker()->createToken($user);

        $this->postJson('/panneau/api/auth/password/reset', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'a new password',
        ])->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('a new password', $user->password));
        $this->assertNotNull($user->getRememberToken());
        $this->assertGuest();
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
        $this->postJson('/panneau/api/auth/login', ['email' => 'ada@example.com', 'password' => 'a new password'])->assertOk();
    }

    public function test_a_wrong_token_is_refused(): void
    {
        $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/password/reset', [
            'token' => 'wrong',
            'email' => 'ada@example.com',
            'password' => 'a new password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', trans(Password::INVALID_TOKEN));
    }

    public function test_the_rules_of_the_password_apply(): void
    {
        $user = $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/password/reset', [
            'token' => Password::broker()->createToken($user),
            'email' => 'ada@example.com',
            'password' => 'short',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);

        $this->assertTrue(Hash::check('secret', $user->fresh()?->password ?? ''));
    }

    public function test_users_the_gate_refuses_are_not_reset(): void
    {
        $eve = $this->user('eve@example.com');

        $this->postJson('/panneau/api/auth/password/reset', [
            'token' => Password::broker()->createToken($eve),
            'email' => 'eve@example.com',
            'password' => 'a new password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', trans(Password::INVALID_TOKEN));

        $this->assertTrue(Hash::check('secret', $eve->fresh()?->password ?? ''));
    }

    public function test_the_action_of_the_config_sets_the_password(): void
    {
        $this->app['config']->set('panneau.auth.reset_password_using', RecordingResetAction::class);
        $user = $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/password/reset', [
            'token' => Password::broker()->createToken($user),
            'email' => 'ada@example.com',
            'password' => 'a new password',
        ])->assertNoContent();

        $this->assertSame(['password' => 'a new password', 'password_confirmation' => 'a new password'], RecordingResetAction::$input);
    }

    public function test_the_routes_need_the_option(): void
    {
        $this->assertTrue(Route::has('panneau.api.auth.password.forgot'));
        $this->assertTrue(Route::has('panneau.api.auth.password.reset'));

        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.auth.password_reset', false);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.auth.password.forgot'));
        $this->assertFalse(Route::has('panneau.api.auth.password.reset'));
        $this->assertTrue(Route::has('panneau.api.auth.login'));
    }
}

class RecordingResetAction implements ResetsUserPasswords
{
    /**
     * @var array<string, mixed>|null
     */
    public static ?array $input = null;

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        self::$input = $input;
    }
}
