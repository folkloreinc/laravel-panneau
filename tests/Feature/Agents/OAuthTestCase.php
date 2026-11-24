<?php

namespace Panneau\Tests\Feature\Agents;

use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use TestApp\Models\OAuthUser;

/**
 * The MCP server with OAuth, through Passport and the routes of laravel/mcp (decision 0028).
 * Concrete tests add `#[WithMigration]` for the `users` table.
 */
abstract class OAuthTestCase extends McpTestCase
{
    protected const REDIRECT = 'http://localhost/callback';

    private static ?string $privateKey = null;

    private static ?string $publicKey = null;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PassportServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('panneau.agents.mcp.oauth.enabled', true);
        $app['config']->set('panneau.agents.mcp.guard', 'api');
        $app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);
        $app['config']->set('auth.providers.users.model', OAuthUser::class);
        $app['config']->set('passport.guard', 'web');
        if (self::$privateKey === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            assert($key !== false);
            openssl_pkey_export($key, $private);
            self::$privateKey = $private;
            self::$publicKey = openssl_pkey_get_details($key)['key'] ?? null;
        }
        $app['config']->set('passport.private_key', self::$privateKey);
        $app['config']->set('passport.public_key', self::$publicKey);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/passport/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/fortify/database/migrations');
    }

    protected function tearDown(): void
    {
        Passport::$scopes = [];
        Passport::$tokensExpireIn = null;
        Passport::$refreshTokensExpireIn = null;

        parent::tearDown();
    }

    protected function createOAuthUser(string $email = 'ada@example.com'): OAuthUser
    {
        return OAuthUser::query()->findOrFail($this->createUser($email)->getKey());
    }

    /**
     * A client registered by `POST /oauth/register`, as an MCP client does.
     */
    protected function registerClient(string $name = 'Claude'): string
    {
        return (string) $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [self::REDIRECT]])
            ->assertCreated()
            ->json('client_id');
    }

    /**
     * The query of an authorization with PKCE.
     *
     * @return array<string, string>
     */
    protected function authorization(string $client, string $scope, string $verifier): array
    {
        return [
            'client_id' => $client,
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'a-state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * The fields the consent page posts, to approve or deny.
     *
     * @return array<string, string>
     */
    protected function consent(string $client): array
    {
        return ['state' => 'a-state', 'client_id' => $client, 'auth_token' => (string) session('authToken')];
    }

    /**
     * Exchanges the code of the redirect to the client for tokens.
     *
     * @return array<string, mixed>
     */
    protected function exchange(string $client, string $location, string $verifier): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('a-state', $query['state'] ?? null);

        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'code' => $query['code'] ?? null,
        ])->assertOk()->json();
    }

    /**
     * The whole flow, for a user and scopes: the access token.
     */
    protected function accessToken(OAuthUser $user, string $scope): string
    {
        $client = $this->registerClient();
        $verifier = str_repeat('v', 64);
        $this->actingAs($user, 'web');
        $this->get('/oauth/authorize?'.http_build_query($this->authorization($client, $scope, $verifier)))->assertOk();
        $location = (string) $this->post('/oauth/authorize', $this->consent($client))->assertRedirect()->headers->get('Location');

        return (string) $this->exchange($client, $location, $verifier)['access_token'];
    }
}
