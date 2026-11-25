<?php

namespace Panneau;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\TwoFactorAuthenticationProvider as FortifyTwoFactorProvider;
use Laravel\Mcp\Server as McpServer;
use Laravel\Passport\Passport;
use Panneau\Agents\Mcp\OAuth;
use Panneau\Auth\Authorizer;
use Panneau\Console\AgentTokenCommand;
use Panneau\Console\ConvertAddressesCommand;
use Panneau\Console\ConvertMoneyCommand;
use Panneau\Console\ConvertPhonesCommand;
use Panneau\Console\InstallCommand;
use Panneau\Console\PruneUploadsCommand;
use Panneau\Console\RoleCommand;
use Panneau\Context\Context;
use Panneau\Context\ContextRegistry;
use Panneau\Contracts\EmbedResolver;
use Panneau\Contracts\UploadStorage;
use Panneau\Dashboard\Dashboard;
use Panneau\Embeds\OEmbedResolver;
use Panneau\Http\Controllers\AggregateController;
use Panneau\Http\Controllers\EmbedController;
use Panneau\Http\Controllers\PasswordResetController;
use Panneau\Http\Controllers\SearchController;
use Panneau\Http\Middleware\ApplyInterfaceLocale;
use Panneau\Http\Middleware\Authenticate;
use Panneau\Http\Middleware\AuthorizeAgents;
use Panneau\Http\Middleware\ResolveContext;
use Panneau\Navigation\Navigation;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\RequestCache;
use Panneau\Resources\ResourceRegistry;
use Panneau\Roles\Contracts\RoleStore;
use Panneau\Roles\DatabaseRoleStore;
use Panneau\Roles\RoleManager;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;
use PragmaRX\Google2FA\Google2FA;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->singleton('panneau', fn ($app) => new Panneau($app));
        $this->app->alias('panneau', Contracts\Panneau::class);

        $this->app->singleton(RepositoryAdapters::class);
        $this->app->singleton(ResourceRegistry::class, function ($app): ResourceRegistry {
            $registry = new ResourceRegistry($app, $app->make(RepositoryAdapters::class));
            $resources = $app['config']->get('panneau.resources', []);
            $registry->register(is_array($resources) ? array_values($resources) : []);

            return $registry;
        });
        $this->app->singleton(Navigation::class);
        $this->app->singleton(Dashboard::class);
        $this->app->singleton(ContextRegistry::class);
        // The values of the current request, filled by the ResolveContext middleware
        $this->app->scoped(Context::class, fn (): Context => new Context);
        // The fields and types of the resources, built once per request and locale (#234)
        $this->app->scoped(RequestCache::class, fn ($app): RequestCache => new RequestCache(fn (): string => (string) $app->getLocale()));
        $this->app->singleton(ItemSerializer::class);
        $this->app->singleton(Authorizer::class);
        $this->app->singletonIf(UploadStorage::class, S3UploadStorage::class);
        $this->app->singleton(Uploads::class);
        $this->app->singletonIf(EmbedResolver::class, OEmbedResolver::class);
        // Fortify's TOTP provider, when Fortify's own service provider isn't loaded
        $this->app->singletonIf(TwoFactorAuthenticationProvider::class, fn ($app): FortifyTwoFactorProvider => new FortifyTwoFactorProvider($app->make(Google2FA::class), $app->make(CacheRepository::class)));

        // The roles module (decision 0014). Bound in any case; used only when it is on.
        $this->app->singletonIf(RoleStore::class, function ($app): RoleStore {
            $store = $app['config']->get('panneau.roles.store', DatabaseRoleStore::class);

            return $app->make(is_string($store) ? $store : DatabaseRoleStore::class);
        });
        $this->app->singleton(RoleManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/panneau.php' => config_path('panneau.php')], 'panneau-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'panneau');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/panneau')], 'panneau-views');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'panneau');
        $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/panneau')], 'panneau-lang');

        $this->app->make(Router::class)->aliasMiddleware('panneau.auth', Authenticate::class);
        $this->app->make(Router::class)->aliasMiddleware('panneau.context', ResolveContext::class);
        $this->app->make(Router::class)->aliasMiddleware('panneau.interface-locale', ApplyInterfaceLocale::class);
        $this->app->make(Router::class)->aliasMiddleware('panneau.agents', AuthorizeAgents::class);
        // Fields can depend on the user: build them again for the next request, or another user
        $this->app->make(Dispatcher::class)->listen([RequestHandled::class, Authenticated::class, Logout::class], function (): void {
            if ($this->app->resolved(RequestCache::class)) {
                $this->app->make(RequestCache::class)->flush();
            }
        });
        // Defined when the rate limiter is first resolved: a limiter of the same name that the
        // project defines in its own provider replaces it.
        $this->callAfterResolving(RateLimiter::class, function (RateLimiter $limiter): void {
            $limiter->for(SearchController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.search.rate_limit'));
            $limiter->for(EmbedController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.embeds.rate_limit'));
            $limiter->for(AggregateController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.aggregates.rate_limit'));
            $limiter->for(AuthorizeAgents::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.agents.rate_limit'));
            // Each route of the auth has its own budget
            $limiter->for(PasswordResetController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.auth.rate_limit', perRoute: true));
        });

        // OAuth for the MCP server (decision 0028), once the project's providers have set theirs
        if ($this->app['config']->get('panneau.agents.mcp.enabled') === true
            && $this->app['config']->get('panneau.agents.mcp.oauth.enabled') === true
            && class_exists(Passport::class) && class_exists(McpServer::class)) {
            $this->app->booted(fn () => OAuth::configure($this->app));
        }

        $migrations = __DIR__.'/../../database/migrations';
        $this->publishesMigrations([$migrations => database_path('migrations')], 'panneau-migrations');
        if ($this->app['config']->get('panneau.uploads.enabled')) {
            $this->loadMigrationsFrom($migrations);
        }

        $roles = __DIR__.'/../../database/roles';
        $this->publishesMigrations([$roles => database_path('migrations')], 'panneau-roles-migrations');
        if ($this->app['config']->get('panneau.roles.enabled') === true) {
            $this->loadMigrationsFrom($roles);
            $this->bootRoles();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PruneUploadsCommand::class, RoleCommand::class, AgentTokenCommand::class, ConvertPhonesCommand::class, ConvertMoneyCommand::class, ConvertAddressesCommand::class]);
        }
    }

    /**
     * The config of the package under the project's, as `mergeConfigFrom()` does, and `agents`
     * merged deeply (#305): a project writes only the keys it changes there. A list, as `tools`,
     * is the project's whole.
     */
    private function mergeConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }
        $config = $this->app->make('config');
        /** @var array<string, mixed> $defaults */
        $defaults = require __DIR__.'/../config/panneau.php';
        $project = $config->get('panneau', []);
        $project = is_array($project) ? $project : [];
        $merged = array_merge($defaults, $project);
        if (is_array($defaults['agents'] ?? null) && is_array($project['agents'] ?? null)) {
            $merged['agents'] = self::mergeDeeply($defaults['agents'], $project['agents']);
        }
        $config->set('panneau', $merged);
    }

    /**
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function mergeDeeply(array $defaults, array $values): array
    {
        foreach ($values as $key => $value) {
            $default = $defaults[$key] ?? null;
            $defaults[$key] = is_array($value) && is_array($default) && $default !== [] && ! array_is_list($default) && ($value === [] || ! array_is_list($value))
                ? self::mergeDeeply($default, $value)
                : $value;
        }

        return $defaults;
    }

    /**
     * The Gate answers the permissions of Panneau, and lets users with a role in, unless the
     * project defines `viewPanneau` itself.
     */
    private function bootRoles(): void
    {
        $this->callAfterResolving(Gate::class, function (Gate $gate): void {
            $gate->before(fn (?Authenticatable $user, string $ability): ?bool => $this->app->make(RoleManager::class)->before($user, $ability));
            if (! $gate->has('viewPanneau')) {
                $gate->define('viewPanneau', fn (Authenticatable $user): bool => $this->app->make(RoleManager::class)->rolesOf($user) !== []);
            }
        });
    }

    /**
     * A limit of requests per minute from a config key, for each user, or each IP address
     * without one: the global search (`panneau.search.rate_limit`), embeds
     * (`panneau.embeds.rate_limit`), the agents (`panneau.agents.rate_limit`), and the password reset and the email verification
     * (`panneau.auth.rate_limit`, per route).
     */
    private function perMinute(Request $request, string $key, bool $perRoute = false): Limit
    {
        $perMinute = $this->app['config']->get($key);
        if (! is_numeric($perMinute) || (int) $perMinute <= 0) {
            return Limit::none();
        }
        $user = $request->user();

        $by = $user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip();

        return Limit::perMinute((int) $perMinute)->by($perRoute ? $request->route()?->getName().'|'.$by : $by);
    }
}
