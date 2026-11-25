<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Http\Responses\SimpleViewResponse;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Http\Problem;
use TestApp\Models\OAuthUserEntity;

/**
 * OAuth for the MCP server (#286): the metadata with both scopes, the consent page, the flow of
 * an MCP client, and the checks of the user.
 */
#[WithMigration]
class OAuthTest extends OAuthTestCase
{
    public function test_the_metadata_names_both_scopes(): void
    {
        foreach (['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/panneau/mcp'] as $uri) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonPath('scopes_supported', ['mcp:use', 'panneau:write'])
                ->assertJsonPath('authorization_servers', ['http://localhost']);
        }
        $this->getJson('/.well-known/oauth-protected-resource/panneau/mcp')->assertJsonPath('resource', 'http://localhost/panneau/mcp');
        foreach (['/.well-known/oauth-authorization-server', '/.well-known/oauth-authorization-server/panneau/mcp'] as $uri) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonPath('scopes_supported', ['mcp:use', 'panneau:write'])
                ->assertJsonPath('authorization_endpoint', 'http://localhost/oauth/authorize')
                ->assertJsonPath('token_endpoint', 'http://localhost/oauth/token')
                ->assertJsonPath('registration_endpoint', 'http://localhost/oauth/register')
                ->assertJsonPath('code_challenge_methods_supported', ['S256']);
        }

        $this->rpc(null, 'tools/list')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://localhost/.well-known/oauth-protected-resource/panneau/mcp"');
    }

    public function test_a_client_reads_with_mcp_use(): void
    {
        $user = $this->createOAuthUser();
        $client = $this->registerClient('Claude');
        $verifier = str_repeat('a', 64);
        $this->actingAs($user, 'web');

        $this->get('/oauth/authorize?'.http_build_query($this->authorization($client, 'mcp:use', $verifier)))
            ->assertOk()
            ->assertSee('Claude wants to connect to your account')
            ->assertSee('It will be able to read your content.')
            ->assertDontSee('read and change')
            ->assertSee('ada@example.com');
        $location = (string) $this->post('/oauth/authorize', $this->consent($client))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?code=', $location);
        $tokens = $this->exchange($client, $location, $verifier);

        $this->rpc($tokens['access_token'], 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item']);
    }

    public function test_the_consent_page_is_in_the_language_the_user_chose(): void
    {
        $this->app['config']->set('panneau.interface_locales', ['en' => 'English', 'fr' => 'Français']);
        $client = $this->registerClient('Claude');
        $this->actingAs($this->createOAuthUser(), 'web');

        $this->withSession(['panneau.locale' => 'fr'])
            ->get('/oauth/authorize?'.http_build_query($this->authorization($client, 'mcp:use panneau:write', str_repeat('c', 64))))
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('Claude veut se connecter à votre compte')
            ->assertSee('Il pourra lire et modifier votre contenu.')
            ->assertSee('Il agira en tant que ada@example.com, avec vos permissions.')
            ->assertSee('Refuser')
            ->assertDontSee('wants to connect');
    }

    public function test_a_client_writes_with_panneau_write(): void
    {
        $user = $this->createOAuthUser();
        $client = $this->registerClient('Claude');
        $verifier = str_repeat('b', 64);
        $this->actingAs($user, 'web');

        $this->get('/oauth/authorize?'.http_build_query($this->authorization($client, 'mcp:use panneau:write', $verifier)))
            ->assertOk()
            ->assertSee('It will be able to read and change your content.');
        $location = (string) $this->post('/oauth/authorize', $this->consent($client))->headers->get('Location');
        $tokens = $this->exchange($client, $location, $verifier);

        $this->rpc($tokens['access_token'], 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item', 'save_item', 'run_action', 'upload_file']);

        // A token that can write can read
        $this->rpc($this->accessToken($user, 'panneau:write'), 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item', 'save_item', 'run_action', 'upload_file']);
    }

    public function test_refusing_answers_the_client_and_issues_nothing(): void
    {
        $client = $this->registerClient();
        $this->actingAs($this->createOAuthUser(), 'web');
        $this->get('/oauth/authorize?'.http_build_query($this->authorization($client, 'mcp:use', str_repeat('c', 64))))->assertOk();

        $location = (string) $this->delete('/oauth/authorize', $this->consent($client))->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith(self::REDIRECT.'?', $location);
        $this->assertSame('access_denied', $query['error'] ?? null);
        $this->assertSame('a-state', $query['state'] ?? null);
        $this->assertSame(0, DB::table('oauth_auth_codes')->count());
        $this->assertSame(0, DB::table('oauth_access_tokens')->count());
    }

    protected function withOwnView($app): void
    {
        $app->singleton(AuthorizationViewResponse::class, fn (): SimpleViewResponse => new SimpleViewResponse(fn (): Response => new Response('The project\'s own consent')));
    }

    #[DefineEnvironment('withOwnView')]
    public function test_a_consent_view_of_the_project_is_kept(): void
    {
        $this->actingAs($this->createOAuthUser(), 'web');

        $this->get('/oauth/authorize?'.http_build_query($this->authorization($this->registerClient(), 'mcp:use', str_repeat('e', 64))))
            ->assertOk()
            ->assertSee('The project\'s own consent', false);
    }

    protected function withLifetimes($app): void
    {
        $app['config']->set('panneau.agents.mcp.oauth.access_token_lifetime', 60);
        $app['config']->set('panneau.agents.mcp.oauth.refresh_token_lifetime', 30);
    }

    #[DefineEnvironment('withLifetimes')]
    public function test_the_lifetimes_of_the_config_reach_the_tokens(): void
    {
        // The OAuth server dates the tokens with the real clock
        $this->accessToken($this->createOAuthUser(), 'mcp:use');

        $this->assertEqualsWithDelta(60, Carbon::now()->diffInMinutes(Carbon::parse(DB::table('oauth_access_tokens')->value('expires_at'))), 1);
        $this->assertEqualsWithDelta(30, Carbon::now()->diffInDays(Carbon::parse(DB::table('oauth_refresh_tokens')->value('expires_at'))), 0.01);
    }

    public function test_an_unverified_address_or_a_missing_two_factor_setup_is_still_refused(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $token = $this->accessToken($this->createOAuthUser(), 'mcp:use panneau:write');

        $this->app['config']->set('panneau.auth.email_verification', true);
        $this->rpc($token, 'tools/list')->assertForbidden()->assertJsonPath('type', Problem::EMAIL_UNVERIFIED);

        $this->app['config']->set('panneau.auth.email_verification', false);
        $this->app['config']->set('panneau.auth.two_factor', true);
        $this->app['config']->set('panneau.auth.two_factor_required', true);
        $this->rpc($token, 'tools/list')->assertForbidden()->assertJsonPath('type', Problem::TWO_FACTOR_REQUIRED);
    }

    public function test_a_guest_goes_to_the_login_of_the_auth_mode(): void
    {
        $authorization = '/oauth/authorize?'.http_build_query($this->authorization($this->registerClient(), 'mcp:use', str_repeat('d', 64)));

        // `external`: the site's login, which can come back to the intended URL
        $this->get($authorization)->assertRedirect('/login');
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', (string) session('url.intended'));
        $this->assertStringContainsString('state=a-state', (string) session('url.intended'));

        // `panneau`: the app's page, which shows its login
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $query = $this->authorization($this->registerClient(), 'mcp:use', str_repeat('d', 64));
        $authorization = '/oauth/authorize?'.http_build_query($query);
        $response = $this->get($authorization)->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost/panneau?next=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $redirect);
        $this->assertSame($authorization, $redirect['next']);
        parse_str((string) parse_url($redirect['next'], PHP_URL_QUERY), $carried);
        $this->assertSame($query, $carried);
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', (string) session('url.intended'));
    }

    public function test_following_next_once_logged_in_reaches_the_consent_page(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $query = $this->authorization($this->registerClient(), 'mcp:use', str_repeat('d', 64));
        $location = (string) $this->get('/oauth/authorize?'.http_build_query($query))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $redirect);

        // The app's page ignores `next` on the server
        $this->withoutVite()->get($location)->assertSuccessful();

        $this->actingAs($this->createOAuthUser())->get($redirect['next'])->assertOk()->assertSee('Claude wants to connect to your account');
    }

    public function test_the_external_login_gets_the_authorization_in_its_parameter(): void
    {
        $this->app['config']->set('panneau.auth.login_url', '/login?locale=fr');
        $this->app['config']->set('panneau.auth.login_redirect_parameter', 'next');
        $query = $this->authorization($this->registerClient(), 'mcp:use panneau:write', str_repeat('e', 64));
        $authorization = '/oauth/authorize?'.http_build_query($query);

        $location = (string) $this->get($authorization)->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('http://localhost/login?locale=fr&next=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $redirect);
        $this->assertSame($authorization, $redirect['next']);
        // The intended URL stays, for a login that follows it
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', (string) session('url.intended'));
    }

    public function test_the_consent_page_names_a_user_that_is_not_a_model(): void
    {
        $client = $this->registerClient('Claude');
        $user = $this->createOAuthUser();
        $entity = new OAuthUserEntity($user);

        $this->actingAs($entity, 'web')
            ->get('/oauth/authorize?'.http_build_query($this->authorization($client, 'mcp:use', str_repeat('f', 64))))
            ->assertOk()
            ->assertSee('It will act as ada@example.com, with your permissions.');
    }
}
