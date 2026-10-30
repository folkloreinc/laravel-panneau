<?php

namespace Panneau;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Panneau as PanneauContract;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Http\Controllers\ActionController;
use Panneau\Http\Controllers\AppController;
use Panneau\Http\Controllers\AuthController;
use Panneau\Http\Controllers\DefinitionController;
use Panneau\Http\Controllers\EmbedController;
use Panneau\Http\Controllers\FieldCheckController;
use Panneau\Http\Controllers\ResourceController;
use Panneau\Http\Controllers\SearchController;
use Panneau\Http\Controllers\UploadController;
use Panneau\Http\Middleware\Authenticate;
use Panneau\Http\Middleware\RenderProblems;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;

class Panneau implements PanneauContract
{
    /**
     * The resource routes that `resourceRoutes()` can register.
     */
    private const RESOURCE_ROUTES = ['index', 'store', 'show', 'update', 'destroy', 'actions', 'check'];

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

        $search = (bool) $config->get('panneau.search.enabled', true);
        $embeds = (bool) $config->get('panneau.embeds.enabled', true);
        $auth = $this->container->make(DefinitionBuilder::class)->auth()['mode'] === 'panneau';

        $router->group([...$group, 'middleware' => [RenderProblems::class, ...$middleware]], function (Router $router) use ($search, $embeds, $auth): void {
            $router->prefix('api')->as('api.')->group(function (Router $router) use ($search, $embeds, $auth): void {
                if ($auth) {
                    // Guests log in, and users the gate refuses can still log out
                    $router->post('auth/login', [AuthController::class, 'login'])
                        ->withoutMiddleware(Authenticate::class)
                        ->name('auth.login');
                    $router->post('auth/logout', [AuthController::class, 'logout'])
                        ->withoutMiddleware(Authenticate::class)
                        ->name('auth.logout');
                }
                $router->get('definition', DefinitionController::class)->name('definition');
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

        if (! $app) {
            return;
        }

        // The page of the app, for every path the app routes on the client. Registered last,
        // without RenderProblems: guests are redirected (or get the login page of the app, in
        // the `panneau` auth mode) and errors are the app's error pages.
        $router->group([...$group, 'middleware' => $middleware], function (Router $router): void {
            $router->get('{path?}', AppController::class)->where('path', '(?!api(?:/|$)).*')->name('app');
        });
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
     * rendered as problems.
     *
     * @param  Closure(Router): void  $routes
     */
    private function apiGroup(Closure $routes): void
    {
        $this->container->make(Router::class)->group(
            ['as' => 'panneau.api.', 'middleware' => [RenderProblems::class]],
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

    public function bootConfig(?Authenticatable $user = null, bool $withDefinition = true): array
    {
        $config = $this->container->make(Config::class);
        $definition = $this->container->make(DefinitionBuilder::class);
        $path = trim((string) $config->get('panneau.path', 'panneau'), '/');
        // Before the definition, for the login page of the app
        $auth = $definition->auth();

        return array_filter([
            'protocol' => DefinitionBuilder::PROTOCOL_VERSION,
            'apiBaseUrl' => $definition->apiBaseUrl(),
            'basePath' => '/'.$path,
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
}
