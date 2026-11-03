<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Contracts\Auth\ResolvesProviderUsers;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\User;

/**
 * Logins with OAuth providers in the `panneau` mode (panneau-js decision 0052), with Socialite
 * mocked.
 */
#[WithMigration]
class ProviderTest extends HttpTestCase
{
    private MockInterface $google;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.auth.mode', 'panneau');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('panneau.auth.providers', [
            'google' => ['label' => 'Google', 'domains' => ['example.com'], 'with' => ['prompt' => 'select_account']],
            'github' => ['label' => 'GitHub'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->guard()->forgetUser();
        Gate::define('viewPanneau', fn ($user): bool => $user->email !== 'eve@example.com');
        $this->google = Mockery::mock(GoogleProvider::class);
        $this->google->shouldReceive('redirectUrl')->andReturnSelf()->byDefault();
        $this->google->shouldReceive('with')->andReturnSelf()->byDefault();
        $this->google->shouldReceive('scopes')->andReturnSelf()->byDefault();
        $socialite = Mockery::mock(Socialite::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($this->google);
        $this->app->instance(Socialite::class, $socialite);
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => 'Ada', 'email' => $email, 'password' => Hash::make('secret')]);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function providerUser(string $email, array $raw = []): SocialiteUser
    {
        return (new SocialiteUser)->setRaw($raw + ['email_verified' => true, 'hd' => 'example.com'])->map(['id' => '1', 'email' => $email, 'name' => 'Ada']);
    }

    private function providerCallback(ProviderUser $user): TestResponse
    {
        $this->google->shouldReceive('user')->andReturn($user);

        return $this->get('/panneau/api/auth/providers/google/callback');
    }

    public function test_the_definition_has_the_providers(): void
    {
        $this->actingAs($this->user('ada@example.com'));

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('auth.providers', [
            ['id' => 'google', 'label' => 'Google', 'url' => '/panneau/api/auth/providers/google/redirect'],
            ['id' => 'github', 'label' => 'GitHub', 'url' => '/panneau/api/auth/providers/github/redirect'],
        ]);
    }

    public function test_the_redirect_goes_to_the_provider_and_keeps_next(): void
    {
        $this->google->shouldReceive('redirectUrl')->atLeast()->once()->with('http://localhost/panneau/api/auth/providers/google/callback')->andReturnSelf();
        // One domain: Google shows its accounts only
        $this->google->shouldReceive('with')->atLeast()->once()->with(['prompt' => 'select_account', 'hd' => 'example.com'])->andReturnSelf();
        $this->google->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));

        $this->get('/panneau/api/auth/providers/google/redirect?next=%2Fpanneau%2Fresources%2Fposts')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->assertSame('/panneau/resources/posts', session('panneau.auth.provider_next'));

        $this->get('/panneau/api/auth/providers/google/redirect?next=https%3A%2F%2Fevil.test');
        $this->assertNull(session('panneau.auth.provider_next'));
    }

    public function test_a_user_logs_in_and_goes_to_next(): void
    {
        $user = $this->user('ada@example.com');
        $this->withSession(['panneau.auth.provider_next' => '/panneau/resources/posts']);

        $this->providerCallback($this->providerUser('ada@example.com'))->assertRedirect('/panneau/resources/posts');

        $this->assertAuthenticatedAs($user);
    }

    public function test_without_next_it_goes_to_the_app(): void
    {
        $this->user('ada@example.com');

        $this->providerCallback($this->providerUser('ada@example.com'))->assertRedirect('/panneau');
        $this->assertAuthenticated();
    }

    public function test_an_address_of_another_domain_is_refused(): void
    {
        $this->user('ada@example.org');

        $this->providerCallback($this->providerUser('ada@example.org', ['hd' => 'example.org']))->assertRedirect('/panneau?authError=domain');
        $this->assertGuest();
    }

    public function test_the_hd_claim_must_be_in_the_domains(): void
    {
        $this->user('ada@example.com');

        // A personal account with an address of the domain
        $this->providerCallback($this->providerUser('ada@example.com', ['hd' => null]))->assertRedirect('/panneau?authError=domain');
        $this->assertGuest();
    }

    public function test_an_unverified_address_is_refused(): void
    {
        $this->user('ada@example.com');

        $this->providerCallback($this->providerUser('ada@example.com', ['email_verified' => false]))->assertRedirect('/panneau?authError=email');
        $this->assertGuest();
    }

    public function test_nobody_is_created(): void
    {
        $this->providerCallback($this->providerUser('new@example.com'))->assertRedirect('/panneau?authError=access');
        $this->assertGuest();
        $this->assertNull(User::query()->where('email', 'new@example.com')->first());
    }

    public function test_users_the_gate_refuses_are_not_logged_in(): void
    {
        $this->user('eve@example.com');

        $this->providerCallback($this->providerUser('eve@example.com'))->assertRedirect('/panneau?authError=access');
        $this->assertGuest();
    }

    public function test_a_failure_of_the_provider_comes_back_to_the_app(): void
    {
        $this->google->shouldReceive('user')->andThrow(new InvalidStateException);

        $this->get('/panneau/api/auth/providers/google/callback')->assertRedirect('/panneau?authError=provider');
    }

    public function test_a_resolver_can_create_the_user(): void
    {
        $this->app['config']->set('panneau.auth.provider_users', CreatingResolver::class);

        $this->providerCallback($this->providerUser('new@example.com'))->assertRedirect('/panneau');

        $this->assertAuthenticatedAs(User::query()->where('email', 'new@example.com')->firstOrFail());
    }

    public function test_only_the_providers_of_the_config_have_routes(): void
    {
        $this->getJson('/panneau/api/auth/providers/twitter/redirect')->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
        $this->assertTrue(Route::has('panneau.api.auth.providers.callback'));

        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.auth.providers', []);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.auth.providers.redirect'));
    }
}

class CreatingResolver implements ResolvesProviderUsers
{
    public function resolve(string $provider, ProviderUser $user, ?Authenticatable $found): ?Authenticatable
    {
        return $found ?? User::query()->create(['name' => (string) $user->getName(), 'email' => (string) $user->getEmail(), 'password' => '']);
    }
}
