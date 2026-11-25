<?php

namespace Panneau\Console;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GenericUser;
use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder as Schema;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens as PassportTokens;
use Laravel\Passport\Passport;
use Laravel\Sanctum\HasApiTokens as SanctumTokens;
use LogicException;
use Panneau\Agents\Mcp\OAuth;
use Panneau\Http\Middleware\AuthorizeAgents;
use Panneau\Upgrade\Unknown;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * `panneau:install --agents` (#305): sets up the MCP server of the agents in a project that
 * runs Panneau, in the order of docs/mcp.md, and checks the pieces it can't write. The token
 * package is the one of the `HasApiTokens` of the users' model: Passport for OAuth, else
 * Sanctum. Running it again changes nothing that is set up. Without interaction, it runs no
 * migration and no Composer: it says what to run.
 *
 * @internal
 */
final class AgentsInstaller
{
    public const MIGRATION = 'upgrade_oauth_clients_to_passport_13';

    private readonly Factory $components;

    private readonly Config $config;

    /**
     * What is left to do by hand.
     *
     * @var list<string>
     */
    private array $todo = [];

    public function __construct(
        private readonly Command $command,
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly Packages $packages,
        private readonly bool $interactive,
        private readonly bool $oauth = false,
        private readonly ?string $user = null,
    ) {
        $this->components = new Factory($command->getOutput());
        $this->config = $app->make(Config::class);
    }

    public function install(): int
    {
        $configPath = $this->app->configPath('panneau.php');
        if (! $this->files->exists($configPath)) {
            $this->components->error('config/panneau.php is missing: run php artisan panneau:install first.');

            return Command::FAILURE;
        }
        if ($this->app->configurationIsCached()) {
            $this->components->error('The config is cached: run php artisan config:clear first, so the installer reads the files.');

            return Command::FAILURE;
        }

        [$provider, $model] = $this->usersProvider();
        $user = $this->retrieveUser($provider);
        $package = $this->package($model, $user);
        if ($package === null) {
            return Command::FAILURE;
        }
        $missing = $this->missingPackages($package);
        if ($missing !== []) {
            return $this->requirePackages($missing);
        }
        $this->components->info(sprintf('Setting up the MCP server of the agents with %s.', $package === 'passport' ? 'OAuth and laravel/passport' : 'personal tokens and laravel/sanctum'));

        [$guard, $created] = $package === 'passport' ? $this->passportGuard($provider) : [$this->sanctumGuard(), false];
        $this->writeConfig($configPath, $package, $guard, $created);
        $this->writeRoutes();
        $migrate = $package === 'passport' ? $this->passportTables($model) : $this->sanctumTable();
        if ($migrate && $this->confirm('Run the migrations now?', false)) {
            $this->command->call('migrate');
        } elseif ($migrate) {
            $this->todo[] = 'Run the migrations: php artisan migrate';
        }
        if ($package === 'passport') {
            $this->passportKeys();
            $this->personalClient($provider, $migrate);
        }

        $this->checkUsers($package, $model, $user, $provider);
        if ($package === 'passport') {
            $this->checkPassport();
        }
        $this->checklist($package);

        return Command::SUCCESS;
    }

    /**
     * The provider of the users of Panneau, and its model when it has one.
     *
     * @return array{string|null, class-string|null}
     */
    private function usersProvider(): array
    {
        $guard = $this->config->get('panneau.auth.guard') ?? $this->config->get('auth.defaults.guard');
        $provider = $this->config->get('auth.guards.'.(is_string($guard) ? $guard : '').'.provider');
        $provider = is_string($provider) ? $provider : null;
        $model = $provider === null ? null : $this->config->get('auth.providers.'.$provider.'.model');

        return [$provider, is_string($model) && class_exists($model) ? $model : null];
    }

    /**
     * What the guard returns for `--user`, or for the user asked for when the provider isn't
     * Eloquent's: with a `repository` provider, an entity that wraps the model.
     */
    private function retrieveUser(?string $provider): ?Authenticatable
    {
        $id = $this->user;
        $eloquent = $provider !== null && $this->config->get('auth.providers.'.$provider.'.driver') === 'eloquent';
        if ($id === null && ! $eloquent && $this->interactive) {
            $answer = $this->command->ask('The users of Panneau aren\'t Eloquent models. A user (id or email) to check what the guard returns? (empty to skip)');
            $id = is_string($answer) && trim($answer) !== '' ? trim($answer) : null;
        }
        if ($id === null || $provider === null) {
            return null;
        }
        try {
            $users = $this->app->make(AuthManager::class)->createUserProvider($provider);
            $user = $users === null ? null : (str_contains($id, '@') ? $users->retrieveByCredentials(['email' => $id]) : $users->retrieveById($id));
        } catch (Throwable $exception) {
            $this->components->warn(sprintf('Could not read the user "%s": %s', $id, $exception->getMessage()));

            return null;
        }
        if ($user === null) {
            $this->components->warn(sprintf('No user "%s".', $id));
        }

        return $user;
    }

    /**
     * `passport` or `sanctum`, from the `HasApiTokens` of the users: the two can't share a model.
     *
     * @param  class-string|null  $model
     */
    private function package(?string $model, ?Authenticatable $user): ?string
    {
        $traits = [...($model === null ? [] : class_uses_recursive($model)), ...($user === null ? [] : class_uses_recursive($user))];
        $passport = in_array(PassportTokens::class, $traits, true) || $user instanceof OAuthenticatable;
        $sanctum = in_array(SanctumTokens::class, $traits, true);
        if ($sanctum && $this->oauth) {
            $this->components->error(sprintf('%s uses the HasApiTokens of laravel/sanctum: OAuth needs Passport\'s instead, and the two can\'t share a model. See "Sanctum or Passport" in docs/mcp.md.', $model ?? ($user === null ? 'The user model' : $user::class)));

            return null;
        }

        return $passport || $this->oauth ? 'passport' : 'sanctum';
    }

    /**
     * @return list<string>
     */
    private function missingPackages(string $package): array
    {
        $missing = array_values(array_filter(['laravel/mcp', 'laravel/'.$package], fn (string $name): bool => ! $this->packages->installed($name)));
        $version = $this->packages->version('laravel/passport');
        if ($package === 'passport' && $version !== null && preg_match('/^\d/', $version) === 1 && version_compare($version, '13.0.0', '<')) {
            $missing[] = 'laravel/passport:^13.0';
        }

        return array_map(fn (string $name): string => $name === 'laravel/passport' ? 'laravel/passport:^13.0' : $name, $missing);
    }

    /**
     * @param  list<string>  $packages
     */
    private function requirePackages(array $packages): int
    {
        $command = ['composer', 'require', ...$packages];
        $this->components->warn('The MCP server needs '.implode(' and ', $packages).'.');
        if (! $this->interactive || ! $this->confirm('Run '.implode(' ', $command).' now?', true)) {
            $this->components->error('Run '.implode(' ', $command).', then php artisan panneau:install --agents again.');

            return Command::FAILURE;
        }
        $process = new Process($command, $this->app->basePath(), timeout: null);
        $process->run(fn (string $type, string $output) => $this->command->getOutput()->write($output));
        if (! $process->isSuccessful()) {
            $this->components->error('Composer failed: see its output above.');

            return Command::FAILURE;
        }
        $this->components->info('Installed. Run php artisan panneau:install --agents again to finish, now that the packages are loaded.');

        return Command::SUCCESS;
    }

    /**
     * A `passport` guard on the users' provider: the project's, or one added to config/auth.php,
     * and whether it was added.
     *
     * @return array{string, bool}
     */
    private function passportGuard(?string $provider): array
    {
        $guards = $this->config->get('auth.guards', []);
        foreach (is_array($guards) ? $guards : [] as $name => $guard) {
            if (is_array($guard) && ($guard['driver'] ?? null) === 'passport' && ($guard['provider'] ?? null) === $provider) {
                return [(string) $name, false];
            }
        }
        $name = isset($guards['passport']) ? 'panneau-agents' : 'passport';
        $path = $this->app->configPath('auth.php');
        if (! $this->files->exists($path)) {
            $this->command->call('config:publish', ['name' => 'auth']);
        }
        $file = new ConfigFile((string) $this->files->get($path));
        $file->set('guards.'.$name, sprintf("['driver' => 'passport', 'provider' => %s]", var_export($provider ?? 'users', true)));
        $this->files->put($path, $file->code());
        $this->config->set('auth.guards.'.$name, ['driver' => 'passport', 'provider' => $provider ?? 'users']);
        $this->components->twoColumnDetail('config/auth.php', sprintf('<fg=green>the "%s" guard</>', $name));

        return [$name, true];
    }

    /**
     * The guard of the server with Sanctum: the configured one when it is a `sanctum` guard.
     */
    private function sanctumGuard(): string
    {
        $guard = $this->config->get('panneau.agents.mcp.guard');

        return is_string($guard) && $this->config->get('auth.guards.'.$guard.'.driver') === 'sanctum' ? $guard : 'sanctum';
    }

    /**
     * The `agents` section of config/panneau.php: only the keys that differ from the package's,
     * which are merged deeply.
     */
    private function writeConfig(string $path, string $package, string $guard, bool $created): void
    {
        $file = new ConfigFile((string) $this->files->get($path));
        $values = ['agents.mcp.enabled' => true, 'agents.mcp.guard' => $guard];
        if ($package === 'passport') {
            $values['agents.mcp.oauth.enabled'] = true;
            // Passport's lifetimes are all its tokens': set only when the agents are its only use
            if ($this->config->get('panneau.agents.mcp.oauth.access_token_lifetime') === null && $created && $this->onlyPassportGuard($guard)) {
                $values['agents.mcp.oauth.access_token_lifetime'] = 60;
            } elseif ($this->config->get('panneau.agents.mcp.oauth.access_token_lifetime') === null) {
                $this->todo[] = 'Passport\'s access tokens last a year: an hour is recommended for the agents, in panneau.agents.mcp.oauth.access_token_lifetime, but it applies to every Passport token of the application.';
            }
        }

        $changed = false;
        foreach ($values as $key => $value) {
            if ($this->config->get('panneau.'.$key) === $value) {
                continue;
            }
            if ($file->has($key) && $file->value($key) === Unknown::Value) {
                $this->todo[] = sprintf('Set panneau.%s to %s: config/panneau.php computes it.', $key, var_export($value, true));

                continue;
            }
            $file->set($key, var_export($value, true));
            $this->config->set('panneau.'.$key, $value);
            $changed = true;
        }
        $tools = $this->config->get('panneau.agents.tools');
        if (! is_array($tools) || $tools === []) {
            $this->todo[] = 'panneau.agents.tools is empty: remove it from config/panneau.php to get the tools of Panneau.';
        }

        if ($changed) {
            $this->files->put($path, $file->code());
        }
        $this->components->twoColumnDetail('config/panneau.php', $changed ? '<fg=green>agents</>' : '<fg=yellow>KEPT</>');
    }

    private function onlyPassportGuard(string $guard): bool
    {
        $guards = $this->config->get('auth.guards', []);
        foreach (is_array($guards) ? $guards : [] as $name => $settings) {
            if ($name !== $guard && is_array($settings) && ($settings['driver'] ?? null) === 'passport') {
                return false;
            }
        }

        return true;
    }

    /**
     * `Panneau::mcpRoutes()` after `Panneau::routes()` in routes/panneau.php, outside the `web`
     * group.
     */
    private function writeRoutes(): void
    {
        $path = $this->app->basePath('routes/panneau.php');
        $web = $this->app->basePath('routes/web.php');
        if ($this->files->exists($web) && str_contains((string) $this->files->get($web), 'mcpRoutes(')) {
            $this->todo[] = 'routes/web.php calls mcpRoutes(): move it to routes/panneau.php, out of the web group, whose CSRF protection refuses the clients (419).';
        }
        if (! $this->files->exists($path)) {
            $this->todo[] = 'Call Panneau::mcpRoutes() after Panneau::routes(), outside the web middleware group: routes/panneau.php is missing.';

            return;
        }
        $code = (string) $this->files->get($path);
        if (str_contains($code, 'mcpRoutes(')) {
            $this->components->twoColumnDetail('routes/panneau.php', '<fg=yellow>KEPT</>');

            return;
        }

        $call = null;
        foreach ((new ParserFactory)->createForHostVersion()->parse($code) ?? [] as $statement) {
            if ($statement instanceof Expression && $statement->expr instanceof StaticCall
                && $statement->expr->name instanceof Identifier && $statement->expr->name->toString() === 'routes') {
                $call = $statement;
            }
        }
        if ($call instanceof Expression && $call->expr instanceof StaticCall) {
            $class = substr($code, $call->expr->class->getStartFilePos(), $call->expr->class->getEndFilePos() - $call->expr->class->getStartFilePos() + 1);
            $code = substr_replace($code, "\n\n// The MCP server of the agents, its OAuth routes and its metadata\n".$class.'::mcpRoutes();', $call->getEndFilePos() + 1, 0);
        } else {
            $code = rtrim($code)."\n\n// The MCP server of the agents, its OAuth routes and its metadata\n\\Panneau\\Support\\Facade::mcpRoutes();\n";
        }
        $this->files->put($path, $code);
        $this->components->twoColumnDetail('routes/panneau.php', '<fg=green>Panneau::mcpRoutes()</>');
    }

    /**
     * Sanctum's table, or its migration. Whether there are migrations to run.
     */
    private function sanctumTable(): bool
    {
        if ($this->schema(null)->hasTable('personal_access_tokens')) {
            return false;
        }
        if ($this->files->glob($this->app->databasePath('migrations/*_create_personal_access_tokens_table.php')) === []) {
            $this->command->call('vendor:publish', ['--tag' => 'sanctum-migrations']);
        }

        return true;
    }

    /**
     * Passport's tables, or their migrations, and the move of a client table older than Passport
     * 13. Whether there are migrations to run.
     *
     * @param  class-string|null  $model
     */
    private function passportTables(?string $model): bool
    {
        $schema = $this->schema($this->passportConnection());
        if (! $schema->hasTable('oauth_clients')) {
            if ($this->files->glob($this->app->databasePath('migrations/*_create_oauth_clients_table.php')) === []) {
                $this->command->call('vendor:publish', ['--tag' => 'passport-migrations']);
            }

            return true;
        }

        $this->checkClientSecrets();
        if ($this->refusesNewClients()) {
            $this->todo[] = 'The ids of the OAuth clients are numbers: set Passport::$clientUuids = false in the boot() of a service provider, or Passport 13 makes clients with UUIDs, which the table refuses.';
        }
        if (! $schema->hasColumn('oauth_clients', 'redirect')) {
            return false;
        }
        if ($this->files->glob($this->app->databasePath('migrations/*_'.self::MIGRATION.'.php')) === []) {
            $relative = 'database/migrations/'.date('Y_m_d_His').'_'.self::MIGRATION.'.php';
            $stub = (string) $this->files->get(__DIR__.'/../../stubs/passport-13-clients.stub');
            $this->files->ensureDirectoryExists($this->app->databasePath('migrations'));
            $this->files->put($this->app->basePath($relative), str_replace('{{ owner }}', $model === null ? 'null' : '\\'.$model.'::class', $stub));
            $this->components->twoColumnDetail($relative, '<fg=green>WRITTEN</>');
        }

        return true;
    }

    /**
     * Numeric ids of clients, older than Passport 13, while Passport makes UUIDs.
     */
    private function refusesNewClients(): bool
    {
        return Passport::$clientUuids
            && str_contains(strtolower($this->schema($this->passportConnection())->getColumnType('oauth_clients', 'id')), 'int');
    }

    /**
     * Passport 13 checks the secrets of the clients as hashes.
     */
    private function checkClientSecrets(): void
    {
        try {
            $secrets = $this->app->make(DatabaseManager::class)->connection($this->passportConnection())
                ->table('oauth_clients')->whereNotNull('secret')->pluck('secret')->all();
        } catch (Throwable) {
            return;
        }
        foreach ($secrets as $secret) {
            if (is_string($secret) && password_get_info($secret)['algo'] === null) {
                $this->todo[] = 'Some secrets of the OAuth clients are in plain text: Passport 13 reads them as hashes. Run php artisan passport:hash.';

                return;
            }
        }
    }

    private function passportKeys(): void
    {
        if (is_string($this->config->get('passport.private_key')) && $this->config->get('passport.private_key') !== ''
            || $this->files->exists(Passport::keyPath('oauth-private.key'))) {
            return;
        }
        if ($this->confirm('Make the keys of Passport in storage/ now?', true)) {
            $this->command->call('passport:keys');
        } else {
            $this->todo[] = 'Make the keys of Passport: php artisan passport:keys';
        }
    }

    /**
     * The client of the personal tokens, for the users' provider.
     */
    private function personalClient(?string $provider, bool $migrate): void
    {
        $provider ??= 'users';
        $command = sprintf('php artisan passport:client --personal --provider=%s --name="Panneau agents"', $provider);
        $schema = $this->schema($this->passportConnection());
        if (! $schema->hasTable('oauth_clients') || $schema->hasColumn('oauth_clients', 'redirect')) {
            $this->todo[] = ($migrate ? 'Once migrated, make' : 'Make').' the client of the personal tokens: '.$command;

            return;
        }
        if ($this->refusesNewClients()) {
            $this->todo[] = 'Once the clients have numeric ids, make the client of the personal tokens: '.$command;

            return;
        }
        try {
            $this->app->make(ClientRepository::class)->personalAccessClient($provider);

            return;
        } catch (Throwable) {
        }
        if ($this->confirm(sprintf('Make the client of the personal tokens for the "%s" provider now?', $provider), true)) {
            $this->command->call('passport:client', ['--personal' => true, '--provider' => $provider, '--name' => 'Panneau agents', '--no-interaction' => true]);
        } else {
            $this->todo[] = 'Make the client of the personal tokens: '.$command;
        }
    }

    /**
     * The users can make the tokens of the package: the trait on the model, and with Passport,
     * `OAuthenticatable` on what the guard returns, which isn't always the model.
     *
     * @param  class-string|null  $model
     */
    private function checkUsers(string $package, ?string $model, ?Authenticatable $user, ?string $provider): void
    {
        $eloquent = $provider !== null && $this->config->get('auth.providers.'.$provider.'.driver') === 'eloquent';
        if ($package === 'sanctum') {
            $subject = $user ?? ($eloquent ? $model : null);
            if ($subject !== null && ! (new ReflectionClass($subject))->hasMethod('createToken')) {
                $this->todo[] = sprintf('Add the HasApiTokens of laravel/sanctum to %s: use Laravel\\Sanctum\\HasApiTokens;', is_object($subject) ? $subject::class : $subject);
            }
        } else {
            if ($eloquent && $model !== null && ! in_array(PassportTokens::class, class_uses_recursive($model), true)) {
                $this->todo[] = sprintf('Add the HasApiTokens of laravel/passport to %s: use Laravel\\Passport\\HasApiTokens;', $model);
            }
            $subject = $user ?? ($eloquent ? $model : null);
            if ($subject !== null && ! is_subclass_of($subject, OAuthenticatable::class) && ! $subject instanceof OAuthenticatable) {
                $this->todo[] = sprintf('%s must implement %s: it is what the guard returns.', is_object($subject) ? $subject::class : $subject, OAuthenticatable::class);
            }
        }
        if ($subject === null) {
            $this->todo[] = sprintf(
                'The users of the "%s" provider aren\'t Eloquent models: check that what the guard returns %s, with --user=<id> to check it here.',
                $provider ?? '',
                $package === 'passport' ? 'implements '.OAuthenticatable::class.' (it can delegate to the model with Passport\'s HasApiTokens)' : 'has createToken() of laravel/sanctum',
            );
        }
    }

    /**
     * What Passport lets through that the agents shouldn't get: consent skipped for every
     * client, a default scope without `mcp:use`, a setup that `Panneau::mcpRoutes()` refuses.
     */
    private function checkPassport(): void
    {
        $class = Passport::clientModel();
        if ($class !== Client::class) {
            try {
                $client = new $class;
                $client->forceFill([
                    'id' => (string) Str::uuid(),
                    'name' => 'An MCP client',
                    'redirect_uris' => json_encode(['http://localhost/callback']),
                    'grant_types' => json_encode(['authorization_code', 'refresh_token']),
                    'revoked' => false,
                ]);
                if ($client->skipsAuthorization(new GenericUser(['id' => 0]), [])) {
                    $this->todo[] = sprintf('%s::skipsAuthorization() is true for a client that registered itself: every MCP client would get a token without the consent of the user. Return true only for your own clients.', $class);
                }
            } catch (Throwable) {
            }
        }

        $scopes = Passport::defaultScopes();
        if ($scopes !== [] && ! in_array(AuthorizeAgents::USE, $scopes, true)) {
            $this->todo[] = sprintf('Passport\'s default scope is "%s", without %s: a client that asks for no scope gets a token the MCP server refuses (401). Add %2$s to it, or make sure the clients ask for it.', implode(' ', $scopes), AuthorizeAgents::USE);
        }

        if ($this->config->get('panneau.auth.mode') === 'external' && ! is_string($this->config->get('panneau.auth.login_redirect_parameter'))) {
            $this->todo[] = 'In the external auth mode, the site\'s login must come back to the OAuth authorization: it redirects to the intended URL (redirect()->intended()), or set panneau.auth.login_redirect_parameter to the query parameter it follows (next…).';
        }

        try {
            OAuth::check($this->config);
        } catch (LogicException $exception) {
            $this->todo[] = $exception->getMessage();
        }
    }

    private function checklist(string $package): void
    {
        $path = trim((string) $this->config->get('panneau.path', 'panneau'), '/').'/'.trim((string) $this->config->get('panneau.agents.mcp.path', 'mcp'), '/');
        $url = rtrim((string) $this->config->get('app.url'), '/').'/'.trim($path, '/');
        $name = Str::slug((string) $this->config->get('app.name')) ?: 'panneau';

        if ($this->todo !== []) {
            $this->command->newLine();
            $this->components->warn('To do by hand:');
            $this->components->bulletList($this->todo);
        }
        $this->command->newLine();
        $this->components->info('The MCP server is at '.$url.'. Next steps:');
        $this->components->bulletList(array_values(array_filter([
            'Check the route: php artisan route:list --name=panneau.mcp',
            $package === 'passport'
                ? sprintf('Connect Claude Code: claude mcp add --transport http %s %s, then /mcp to sign in.', $name, $url)
                : sprintf('Make a token: php artisan panneau:agent-token <id or email> [--write], then claude mcp add --transport http %s %s --header "Authorization: Bearer <token>"', $name, $url),
            'Who may use the agents: everyone the viewPanneau gate lets in, unless the project defines usePanneauAgents.',
            $package === 'passport' ? 'On each server: php artisan passport:keys (or PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY), then the client of the personal tokens.' : null,
            $package === 'passport' ? 'A web application firewall can refuse the http://localhost redirect URIs of local clients on /oauth/* (ModSecurity with the OWASP CRS, rules 931xxx and 934xxx): a 500 of the web server before PHP. Allow them on those paths.' : null,
            'More in docs/mcp.md of folklore/laravel-panneau.',
        ])));
    }

    /**
     * Without interaction, the default answer.
     */
    private function confirm(string $question, bool $default): bool
    {
        return $this->interactive ? $this->command->confirm($question, $default) : $default;
    }

    private function passportConnection(): ?string
    {
        $connection = $this->config->get('passport.connection');

        return is_string($connection) ? $connection : null;
    }

    private function schema(?string $connection): Schema
    {
        return $this->app->make(DatabaseManager::class)->connection($connection)->getSchemaBuilder();
    }
}
