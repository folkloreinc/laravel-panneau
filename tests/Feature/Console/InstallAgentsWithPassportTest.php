<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Tests\Concerns\InstallsInAProject;
use Panneau\Tests\Feature\Agents\OAuthTestCase;
use TestApp\Models\OAuthUser;
use TestApp\Passport\EntityUserProvider;
use TestApp\Passport\TrustingClient;

/**
 * `panneau:install --agents` with OAuth and Passport (#305).
 */
#[WithMigration]
class InstallAgentsWithPassportTest extends OAuthTestCase
{
    use InstallsInAProject;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->makeProject($app);
        // The project before the installer: Passport's users, no passport guard, OAuth off
        $app['config']->set('auth.guards', ['web' => ['driver' => 'session', 'provider' => 'users']]);
        $app['config']->set('panneau.agents.mcp.enabled', false);
        $app['config']->set('panneau.agents.mcp.oauth.enabled', false);
        $app['config']->set('panneau.agents.mcp.guard', 'sanctum');
        $app['config']->set('panneau.auth.mode', 'panneau');
    }

    protected function tearDown(): void
    {
        Passport::useClientModel(Client::class);
        Passport::$defaultScope = '';
        Passport::$clientUuids = true;

        parent::tearDown();
    }

    public function test_it_sets_up_oauth_from_the_model_of_passport(): void
    {
        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertStringContainsString('OAuth and laravel/passport', $output);
        $this->assertStringContainsString('claude mcp add --transport http laravel http://localhost/panneau/mcp, then /mcp to sign in', $output);
        $this->assertStringContainsString('ModSecurity', $output);

        $this->assertSame(['driver' => 'passport', 'provider' => 'users'], $this->projectConfig('auth')['guards']['passport']);
        $mcp = $this->projectConfig('panneau')['agents']['mcp'];
        $this->assertTrue($mcp['enabled']);
        $this->assertSame('passport', $mcp['guard']);
        $this->assertTrue($mcp['oauth']['enabled']);
        $this->assertSame(60, $mcp['oauth']['access_token_lifetime']);
        $this->assertStringContainsString('Panneau::mcpRoutes();', $this->read('routes/panneau.php'));

        $client = Passport::client()->newQuery()->sole();
        $this->assertTrue($client->hasGrantType('personal_access'));
        $this->assertSame('users', $client->provider);
    }

    public function test_it_makes_the_keys_of_passport(): void
    {
        $this->app['config']->set('passport.private_key', null);
        $this->app['config']->set('passport.public_key', null);

        $this->install(['--agents' => true, '--no-interaction' => true]);

        $this->assertFileExists($this->project.'/storage/oauth-private.key');
        $this->assertFileExists($this->project.'/storage/oauth-public.key');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $files = [$this->read('config/panneau.php'), $this->read('config/auth.php'), $this->read('routes/panneau.php')];

        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertStringNotContainsString('To do by hand', $output);

        $this->assertSame($files, [$this->read('config/panneau.php'), $this->read('config/auth.php'), $this->read('routes/panneau.php')]);
        $this->assertSame(1, Passport::client()->newQuery()->count());
    }

    public function test_an_existing_passport_guard_is_kept_with_its_lifetimes(): void
    {
        $this->app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);

        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertStringContainsString('it applies to every Passport token of the application', $output);

        $this->assertArrayNotHasKey('passport', $this->projectConfig('auth')['guards']);
        $mcp = $this->projectConfig('panneau')['agents']['mcp'];
        $this->assertSame('api', $mcp['guard']);
        $this->assertNull($mcp['oauth']['access_token_lifetime']);
    }

    public function test_it_moves_the_clients_of_passport_12_and_keeps_their_ids(): void
    {
        $this->oldClients();

        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertStringContainsString('Run the migrations: php artisan migrate', $output);
        $this->assertStringContainsString('Passport::$clientUuids = false', $output);
        $this->assertStringContainsString('php artisan passport:hash', $output);
        $this->assertStringContainsString('Once migrated, make the client of the personal tokens: php artisan passport:client --personal --provider=users', $output);
        $migrations = glob($this->project.'/database/migrations/*_upgrade_oauth_clients_to_passport_13.php') ?: [];
        $this->assertCount(1, $migrations);

        (require $migrations[0])->up();

        $this->assertFalse(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertFalse(Schema::hasColumn('oauth_clients', 'redirect'));
        $site = Passport::client()->newQuery()->findOrFail(7);
        $this->assertSame(['https://example.com/a', 'https://example.com/b'], $site->redirect_uris);
        $this->assertSame(['authorization_code', 'refresh_token'], $site->grant_types);
        $this->assertSame((new OAuthUser)->getMorphClass(), $site->owner_type);
        $this->assertSame(1, (int) $site->owner_id);
        $this->assertSame(['personal_access'], Passport::client()->newQuery()->findOrFail(8)->grant_types);
        $this->assertSame(['client_credentials'], Passport::client()->newQuery()->findOrFail(9)->grant_types);
        $this->assertTrue(Passport::client()->newQuery()->findOrFail(9)->firstParty());

        // Once migrated with numeric ids, a second run writes no other migration and makes the
        // personal client
        Passport::$clientUuids = false;
        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertCount(1, glob($this->project.'/database/migrations/*_upgrade_oauth_clients_to_passport_13.php') ?: []);
        $this->assertStringNotContainsString('client of the personal tokens:', $output);
        $this->assertTrue($this->app->make(ClientRepository::class)->personalAccessClient('users')->hasGrantType('personal_access'));
    }

    public function test_it_warns_about_what_passport_lets_through(): void
    {
        Passport::useClientModel(TrustingClient::class);
        Passport::setDefaultScope(['profile', 'email']);
        $this->app['config']->set('panneau.auth.mode', 'external');

        $output = $this->install(['--agents' => true, '--no-interaction' => true]);
        $this->assertStringContainsString('TrustingClient::skipsAuthorization() is true for a client that registered itself', $output);
        $this->assertStringContainsString('Passport\'s default scope is "profile email", without mcp:use', $output);
        $this->assertStringContainsString('panneau.auth.login_redirect_parameter', $output);
    }

    public function test_it_checks_what_the_guard_returns_when_it_is_not_a_model(): void
    {
        Auth::provider('entities', fn ($app, array $config): EntityUserProvider => new EntityUserProvider($app['hash'], OAuthUser::class));
        $this->app['config']->set('auth.providers.users', ['driver' => 'entities']);
        $user = $this->createOAuthUser();

        // Without a user, the installer can't tell, and says so
        $output = $this->install(['--agents' => true, '--oauth' => true, '--no-interaction' => true]);
        $this->assertStringContainsString('The users of the "users" provider aren\'t Eloquent models', $output);

        // With one, Passport is picked from what the guard returns
        $output = $this->install(['--agents' => true, '--user' => (string) $user->getKey(), '--no-interaction' => true]);
        $this->assertStringContainsString('OAuth and laravel/passport', $output);
        $this->assertStringNotContainsString('aren\'t Eloquent models', $output);
        $this->assertStringNotContainsString('must implement', $output);
    }

    /**
     * Runs the installer, which must succeed, and gives its output: unlike the mocked output of
     * artisan(), not wrapped at a width.
     *
     * @param  array<string, mixed>  $options
     */
    private function install(array $options): string
    {
        $this->assertSame(0, Artisan::call('panneau:install', $options));

        return Artisan::output();
    }

    /**
     * The clients of Passport 12: numeric ids, a user, the redirect URIs in one column, and the
     * table of the personal access clients.
     */
    private function oldClients(): void
    {
        Schema::drop('oauth_clients');
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('secret', 100)->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect');
            $table->boolean('personal_access_client');
            $table->boolean('password_client');
            $table->boolean('revoked');
            $table->timestamps();
        });
        Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });
        $client = ['provider' => null, 'personal_access_client' => false, 'password_client' => false, 'revoked' => false];
        DB::table('oauth_clients')->insert([
            [...$client, 'id' => 7, 'user_id' => 1, 'name' => 'Site', 'secret' => null, 'redirect' => 'https://example.com/a,https://example.com/b'],
            [...$client, 'id' => 8, 'user_id' => null, 'name' => 'Personal', 'secret' => 'plain', 'redirect' => '', 'personal_access_client' => true],
            [...$client, 'id' => 9, 'user_id' => null, 'name' => 'Machine', 'secret' => bcrypt('secret'), 'redirect' => ''],
        ]);
    }
}
