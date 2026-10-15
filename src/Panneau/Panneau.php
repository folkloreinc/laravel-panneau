<?php

namespace Panneau;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Panneau as PanneauContract;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Http\Controllers\ActionController;
use Panneau\Http\Controllers\DefinitionController;
use Panneau\Http\Controllers\ResourceController;
use Panneau\Http\Middleware\RenderProblems;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

class Panneau implements PanneauContract
{
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

    public function routes(): void
    {
        $config = $this->container->make(Config::class);
        $router = $this->container->make(Router::class);
        $middleware = $config->get('panneau.middleware', ['web', 'panneau.auth']);
        $domain = $config->get('panneau.domain');

        $router->group(array_filter([
            'prefix' => trim((string) $config->get('panneau.path', 'panneau'), '/'),
            'domain' => is_string($domain) ? $domain : null,
            'middleware' => [RenderProblems::class, ...(is_array($middleware) ? $middleware : [])],
            'as' => 'panneau.',
        ]), function (Router $router): void {
            $router->prefix('api')->as('api.')->group(function (Router $router): void {
                $router->get('definition', DefinitionController::class)->name('definition');
                $router->get('resources/{resource}', [ResourceController::class, 'index'])->name('resources.index');
                $router->post('resources/{resource}', [ResourceController::class, 'store'])->name('resources.store');
                $router->post('resources/{resource}/actions/{action}', ActionController::class)->name('resources.actions');
                $router->get('resources/{resource}/{id}', [ResourceController::class, 'show'])->name('resources.show');
                $router->patch('resources/{resource}/{id}', [ResourceController::class, 'update'])->name('resources.update');
                $router->delete('resources/{resource}/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy');
            });
        });
    }

    public function definition(?Authenticatable $user = null): array
    {
        return $this->container->make(DefinitionBuilder::class)->build($user);
    }
}
