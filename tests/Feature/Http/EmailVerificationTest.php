<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\Notifications\VerifyEmail;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\User;

/**
 * The email verification of the `panneau` mode (panneau-js decision 0048).
 */
#[WithMigration]
class EmailVerificationTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('panneau.auth.email_verification', true);
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
        BaseVerifyEmail::$toMailCallback = null;
        VerifyEmail::$toMailCallback = null;

        parent::tearDown();
    }

    private function user(string $email, bool $verified = false): User
    {
        return User::query()->create([
            'name' => ucfirst(strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => Hash::make('secret'),
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function token(User $user): string
    {
        return $this->app->make(EmailVerification::class)->token($user);
    }

    public function test_users_whose_address_is_not_verified_are_refused(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::EMAIL_UNVERIFIED);
        $this->getJson('/panneau/api/resources/posts')->assertJsonPath('type', Problem::EMAIL_UNVERIFIED);
    }

    public function test_they_get_the_page_of_the_app_without_the_definition(): void
    {
        $this->withoutVite();
        $this->actingAs($this->user('ada@example.com'));

        $html = (string) $this->get('/panneau/resources/posts')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="panneau-boot">(.*?)</script>#s', $html, $matches));
        $boot = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($boot);
        $this->assertArrayNotHasKey('definition', $boot);
        $this->assertSame(['mode' => 'panneau', 'loginUrl' => null, 'logoutUrl' => null, 'emailVerification' => true], $boot['auth']);
    }

    public function test_verified_users_get_in(): void
    {
        $this->actingAs($this->user('ada@example.com', verified: true));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.emailVerification', true);
    }

    public function test_users_that_do_not_verify_their_address_get_in(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'ada@example.com']));

        $this->getJson('/panneau/api/definition')->assertOk();
    }

    public function test_nobody_is_refused_without_the_option(): void
    {
        $this->app['config']->set('panneau.auth.email_verification', false);
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('auth.emailVerification');
    }

    public function test_a_link_is_sent_to_the_page_of_the_app(): void
    {
        Notification::fake();
        $user = $this->user('ada@example.com');
        $this->actingAs($user);

        $this->postJson('/panneau/api/auth/email/verification')->assertNoContent();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification): bool {
            $this->assertStringStartsWith('http://localhost/panneau/auth/verify-email?token=', $notification->url);

            return true;
        });
    }

    public function test_the_email_has_its_own_callbacks(): void
    {
        BaseVerifyEmail::toMailUsing(fn (): MailMessage => (new MailMessage)->line('The site'));

        $mail = (new VerifyEmail('https://example.com/panneau/auth/verify-email?token=t'))->toMail($this->user('ada@example.com'));

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('https://example.com/panneau/auth/verify-email?token=t', $mail->actionUrl);
    }

    public function test_no_link_is_sent_to_a_verified_address(): void
    {
        Notification::fake();
        $this->actingAs($this->user('ada@example.com', verified: true));

        $this->postJson('/panneau/api/auth/email/verification')->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_guests_and_users_the_gate_refuses_get_no_link(): void
    {
        Notification::fake();

        $this->postJson('/panneau/api/auth/email/verification')->assertUnauthorized();
        $this->actingAs($this->user('eve@example.com'));
        $this->postJson('/panneau/api/auth/email/verification')->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_the_token_of_the_link_verifies_the_address_without_a_session(): void
    {
        Event::fake([Verified::class]);
        $user = $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/email/verify', ['token' => $this->token($user)])->assertNoContent();

        $this->assertTrue($user->fresh()?->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
        $this->assertGuest();
        // Again: already verified
        $this->postJson('/panneau/api/auth/email/verify', ['token' => $this->token($user)])->assertNoContent();
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_a_token_that_expired_is_refused(): void
    {
        $user = $this->user('ada@example.com');
        $token = $this->token($user);

        $this->travel(61)->minutes();

        $this->postJson('/panneau/api/auth/email/verify', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['token']]);
        $this->assertFalse($user->fresh()?->hasVerifiedEmail());
    }

    public function test_a_token_of_a_previous_address_is_refused(): void
    {
        $user = $this->user('ada@example.com');
        $token = $this->token($user);
        $user->forceFill(['email' => 'ada@example.org'])->save();

        $this->postJson('/panneau/api/auth/email/verify', ['token' => $token])->assertStatus(422);
        $this->assertFalse($user->fresh()?->hasVerifiedEmail());
    }

    public function test_a_token_that_was_not_made_by_the_app_is_refused(): void
    {
        $this->user('ada@example.com');

        $this->postJson('/panneau/api/auth/email/verify', ['token' => 'unknown'])->assertStatus(422);
        $this->postJson('/panneau/api/auth/email/verify', ['token' => encrypt(['id' => 1])])->assertStatus(422);
        $this->postJson('/panneau/api/auth/email/verify', [])->assertStatus(422);
    }

    public function test_the_routes_need_the_option(): void
    {
        $this->assertTrue(Route::has('panneau.api.auth.email.verification'));
        $this->assertTrue(Route::has('panneau.api.auth.email.verify'));

        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.auth.email_verification', false);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.auth.email.verification'));
        $this->assertFalse(Route::has('panneau.api.auth.email.verify'));
    }
}
