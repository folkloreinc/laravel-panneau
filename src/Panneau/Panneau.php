<?php

namespace Panneau;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use Panneau\Auth\Authorizer;
use Panneau\Context\ContextParam;
use Panneau\Context\ContextRegistry;
use Panneau\Context\ContextResolver;
use Panneau\Contracts\Panneau as PanneauContract;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Exceptions\ContextRefusedException;
use Panneau\Http\Controllers\ActionController;
use Panneau\Http\Controllers\AppController;
use Panneau\Http\Controllers\AuthController;
use Panneau\Http\Controllers\ContextOptionsController;
use Panneau\Http\Controllers\DefinitionController;
use Panneau\Http\Controllers\EmailVerificationController;
use Panneau\Http\Controllers\EmbedController;
use Panneau\Http\Controllers\FieldCheckController;
use Panneau\Http\Controllers\PasswordResetController;
use Panneau\Http\Controllers\ResourceController;
use Panneau\Http\Controllers\SearchController;
use Panneau\Http\Controllers\UploadController;
use Panneau\Http\Middleware\Authenticate;
use Panneau\Http\Middleware\RenderProblems;
use Panneau\Http\Middleware\ResolveContext;
use Panneau\Navigation\Navigation;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;

class Panneau implements PanneauContract
{
    /**
     * The resource routes that `resourceRoutes()` can register.
     */
    private const RESOURCE_ROUTES = ['index', 'store', 'show', 'update', 'destroy', 'duplicate', 'actions', 'check'];

    public function __construct(private readonly Container $container) {}

    public function resources(array $resources): static
    {
        $this->container->make(ResourceRegistry::class)->register($resources);

        return $this;
    }

    public function resource(string $id): ?Resource
    {
        return $this->container->make(ResourceRegistry::class)->find($id);
    }

    public function pages(array|Closure $pages): static
    {
        $this->container->make(Navigation::class)->addPages($pages);

        return $this;
    }

    public function menu(string $name, array|Closure $items): static
    {
        $this->container->make(Navigation::class)->setMenu($name, $items);

        return $this;
    }

    /**
     * @param  list<ContextParam>|Closure  $params
     */
    public function context(array|Closure $params): static
    {
        $this->container->make(ContextRegistry::class)->declare($params);

        return $this;
    }

    public function repositoryAdapter(string $contract, string|Closure $adapter): static
    {
        $this->container->make(RepositoryAdapters::class)->register($contract, $adapter);

        return $this;
    }

    public function allowWithoutPolicies(bool $allow = true): static
    {
        $this->container->make(Authorizer::class)->allowWithoutPolicies($allow);

        return $this;
    }

    public function routes(bool $app = true): void
    {
        $config = $this->container->make(Config::class);
        $router = $this->container->make(Router::class);
        $middleware = $config->get('panneau.middleware', ['web', 'panneau.auth']);
        $middleware = is_array($middleware) ? $middleware : [];
        $domain = $config->get('panneau.domain');
        $group = array_filter([
            'prefix' => trim((string) $config->get('panneau.path', 'panneau'), '/'),
            'domain' => is_string($domain) ? $domain : null,
            'as' => 'panneau.',
        ]);

        $before = $router->getRoutes()->getRoutes();
        // The prefix of the caller's group, such as `tenants/{tenant}`
        $outerPrefix = trim($router->getLastGroupPrefix(), '/');

        $search = (bool) $config->get('panneau.search.enabled', true);
        $embeds = (bool) $config->get('panneau.embeds.enabled', true);
        $auth = $this->container->make(DefinitionBuilder::class)->auth();

        $router->group([...$group, 'middleware' => [RenderProblems::class, ...$middleware]], function (Router $router) use ($search, $embeds, $auth): void {
            // The context after the authentication: values are checked for the user
            $router->prefix('api')->as('api.')->middleware(ResolveContext::class)->group(function (Router $router) use ($search, $embeds, $auth): void {
                if ($auth['mode'] === 'panneau') {
                    $this->registerAuthRoutes($router, $auth);
                }
                $router->get('definition', DefinitionController::class)->name('definition');
                $router->get('context/{param}/options', ContextOptionsController::class)->name('context.options');
                if ($search) {
                    $router->get('search', SearchController::class)
                        ->middleware(ThrottleRequests::using(SearchController::RATE_LIMITER))
                        ->name('search');
                }
                if ($embeds) {
                    $router->get('embeds', EmbedController::class)
                        ->middleware(ThrottleRequests::using(EmbedController::RATE_LIMITER))
                        ->name('embeds');
                }
                $this->registerResourceRoutes($router, null, self::RESOURCE_ROUTES);
                $this->registerUploadRoutes($router);
            });
        });

        if ($app) {
            // The page of the app, for every path the app routes on the client. Registered last,
            // without RenderProblems: guests are redirected (or get the login page of the app, in
            // the `panneau` auth mode) and errors are the app's error pages.
            $router->group([...$group, 'middleware' => $middleware], function (Router $router): void {
                $router->get('{path?}', AppController::class)->where('path', '(?!api(?:/|$)).*')->name('app');
            });
        }

        $this->moveSiteRoutesAfter($router, $before, trim($outerPrefix.'/'.($group['prefix'] ?? ''), '/'));
    }

    /**
     * The auth routes of the `panneau` mode. Guests log in, users the gate refuses can still log
     * out, and those whose address isn't verified can verify it: none of them go through the
     * authentication of Panneau or the context.
     *
     * @param  array{passwordReset?: bool, emailVerification?: bool}  $auth
     */
    private function registerAuthRoutes(Router $router, array $auth): void
    {
        $outside = [Authenticate::class, ResolveContext::class];
        $throttle = ThrottleRequests::using(PasswordResetController::RATE_LIMITER);
        $router->post('auth/login', [AuthController::class, 'login'])->withoutMiddleware($outside)->name('auth.login');
        $router->post('auth/logout', [AuthController::class, 'logout'])->withoutMiddleware($outside)->name('auth.logout');
        if (($auth['passwordReset'] ?? false) === true) {
            $router->post('auth/password/forgot', [PasswordResetController::class, 'forgot'])
                ->withoutMiddleware($outside)->middleware($throttle)->name('auth.password.forgot');
            $router->post('auth/password/reset', [PasswordResetController::class, 'reset'])
                ->withoutMiddleware($outside)->middleware($throttle)->name('auth.password.reset');
        }
        if (($auth['emailVerification'] ?? false) === true) {
            $router->post('auth/email/verification', [EmailVerificationController::class, 'send'])
                ->withoutMiddleware($outside)->middleware($throttle)->name('auth.email.verification');
            $router->post('auth/email/verify', [EmailVerificationController::class, 'verify'])
                ->withoutMiddleware($outside)->middleware($throttle)->name('auth.email.verify');
        }
    }

    /**
     * Moves the routes of the site registered before Panneau's, outside Panneau's path, after
     * them: a page route of the site such as `/{page}` would catch `/panneau`, when the site
     * registers its routes first (a `RouteServiceProvider` listed before the provider that
     * loads Panneau's). Routes of the site under Panneau's path, with the prefix of the
     * caller's group, keep their precedence. A collection of another class (cached routes, a
     * router's own) is left as it is.
     *
     * @param  list<Route>  $before
     */
    private function moveSiteRoutesAfter(Router $router, array $before, string $prefix): void
    {
        $routes = $router->getRoutes();
        if ($prefix === '' || $before === [] || get_class($routes) !== RouteCollection::class) {
            return;
        }
        $outside = array_filter($before, function (Route $route) use ($prefix): bool {
            $uri = trim($route->uri(), '/');

            return $uri !== $prefix && ! str_starts_with($uri, $prefix.'/');
        });
        if ($outside === []) {
            return;
        }
        $moved = array_flip(array_map(spl_object_id(...), $outside));
        $collection = new RouteCollection;
        foreach ($routes->getRoutes() as $route) {
            if (! isset($moved[spl_object_id($route)])) {
                $collection->add($route);
            }
        }
        foreach ($outside as $route) {
            $collection->add($route);
        }
        $router->setRoutes($collection);
    }

    public function uploadRoutes(): void
    {
        $this->apiGroup(fn (Router $router) => $this->registerUploadRoutes($router));
    }

    public function resourceRoutes(?array $resources = null, ?array $only = null): void
    {
        if ($resources === []) {
            throw new InvalidArgumentException('No resources to register routes for: pass null for all of them.');
        }
        $only ??= self::RESOURCE_ROUTES;
        $unknown = array_diff($only, self::RESOURCE_ROUTES);
        if (count($unknown) > 0) {
            throw new InvalidArgumentException(sprintf(
                'No resource route "%s": use %s.',
                implode('", "', $unknown),
                implode(', ', self::RESOURCE_ROUTES),
            ));
        }

        $this->apiGroup(fn (Router $router) => $this->registerResourceRoutes($router, $resources, $only));
    }

    /**
     * A group of API routes in the caller's group, named `panneau.api.*`, with their errors
     * rendered as problems, and the current context, after the middleware of the caller's
     * group (which authenticates).
     *
     * @param  Closure(Router): void  $routes
     */
    private function apiGroup(Closure $routes): void
    {
        $this->container->make(Router::class)->group(
            ['as' => 'panneau.api.', 'middleware' => [RenderProblems::class, ResolveContext::class]],
            $routes,
        );
    }

    /**
     * @param  list<string>|null  $resources
     * @param  list<string>  $only
     */
    private function registerResourceRoutes(Router $router, ?array $resources, array $only): void
    {
        $routes = [
            'index' => fn () => $router->get('resources/{resource}', [ResourceController::class, 'index'])->name('resources.index'),
            'store' => fn () => $router->post('resources/{resource}', [ResourceController::class, 'store'])->name('resources.store'),
            'actions' => fn () => $router->post('resources/{resource}/actions/{action}', ActionController::class)->name('resources.actions'),
            'check' => fn () => $router->post('resources/{resource}/fields/{field}/check', FieldCheckController::class)->name('resources.fields.check'),
            'show' => fn () => $router->get('resources/{resource}/{id}', [ResourceController::class, 'show'])->name('resources.show'),
            'update' => fn () => $router->patch('resources/{resource}/{id}', [ResourceController::class, 'update'])->name('resources.update'),
            'destroy' => fn () => $router->delete('resources/{resource}/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy'),
            'duplicate' => fn () => $router->post('resources/{resource}/{id}/duplicate', [ResourceController::class, 'duplicate'])->name('resources.duplicate'),
        ];
        foreach ($routes as $name => $register) {
            if (! in_array($name, $only, true)) {
                continue;
            }
            $route = $register();
            if ($resources !== null) {
                $route->where('resource', implode('|', array_map(fn (string $id): string => preg_quote($id, '#'), $resources)));
            }
        }
    }

    private function registerUploadRoutes(Router $router): void
    {
        if (! $this->container->make(Uploads::class)->enabled()) {
            return;
        }
        $router->post('uploads/sign', [UploadController::class, 'sign'])->name('uploads.sign');
        $router->post('uploads/{upload}/complete', [UploadController::class, 'complete'])->name('uploads.complete');
    }

    public function bootConfig(?Authenticatable $user = null, bool $withDefinition = true, ?string $path = null): array
    {
        $config = $this->container->make(Config::class);
        $definition = $this->container->make(DefinitionBuilder::class);
        $basePath = trim((string) $config->get('panneau.path', 'panneau'), '/');
        // Before the definition, for the login page of the app
        $auth = $definition->auth();
        if ($withDefinition) {
            $withDefinition = $this->resolvePathContext($user, $path);
        }

        return array_filter([
            'protocol' => DefinitionBuilder::PROTOCOL_VERSION,
            'apiBaseUrl' => $definition->apiBaseUrl(),
            'basePath' => '/'.$basePath,
            'locale' => $this->container->make(Application::class)->getLocale(),
            'definition' => $withDefinition ? $definition->build($user) : null,
            'auth' => $auth,
            'loginUrl' => $auth['loginUrl'],
        ], fn (mixed $value): bool => $value !== null);
    }

    public function definition(?Authenticatable $user = null): array
    {
        return $this->container->make(DefinitionBuilder::class)->build($user);
    }

    /**
     * Fills the current context with the values of the prefix of a path of the app, checked for
     * the user, with the defaults of the params without a value: the definition of the boot
     * config is built for them. False when the user can't use a value: the app fetches the
     * definition, and gets the `403` context problem.
     */
    private function resolvePathContext(?Authenticatable $user, ?string $path): bool
    {
        $registry = $this->container->make(ContextRegistry::class);
        if (! $registry->declared()) {
            return true;
        }
        try {
            $this->container->make(ContextResolver::class)->resolve(
                ContextResolver::parsePath($path ?? '', $registry->params($user)),
                $user,
                $this->container->make(Request::class),
            );
        } catch (ContextRefusedException) {
            return false;
        }

        return true;
    }
}
