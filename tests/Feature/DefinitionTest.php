<?php

namespace Panneau\Tests\Feature;

use Illuminate\Support\Collection;
use Panneau\Tests\TestCase;
use TestApp\Resources\PagesResource;

class DefinitionTest extends TestCase
{
    protected $panneau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
    }

    /**
     * Test definition resources
     *
     * @return void
     */
    public function test_resources()
    {
        $this->panneau->resources([PagesResource::class]);

        $definition = $this->panneau->definition();

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(1, $resources->count());

        $this->panneau->resources([PagesResource::class]);

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(2, $resources->count());

        $this->assertContainsOnlyInstancesOf(PagesResource::class, $resources);
    }

    /**
     * Test definition routes
     *
     * @return void
     */
    public function test_routes()
    {
        $this->panneau->resources([PagesResource::class]);

        $definition = $this->panneau->definition();

        $routes = $definition->routes();

        $this->assertInstanceOf(Collection::class, $routes);

        // Test that route key is the route name and that all routes start with the prefix
        foreach ($routes as $routeName => $route) {
            $this->assertEquals($routeName, $route->getName());
            $this->assertMatchesRegularExpression(
                '%'.config('panneau.routes.prefix').'%',
                $routeName
            );
        }

        // Test that all resources routes are present
        $routesToTest = [
            'panneau.resources.index',
            'panneau.resources.create',
            'panneau.resources.store',
            'panneau.resources.show',
            'panneau.resources.edit',
            'panneau.resources.update',
            'panneau.resources.destroy',
            'panneau.resources.delete',
            'panneau.auth.login',
            'panneau.auth.logout',
        ];
        foreach ($routesToTest as $routeName) {
            $this->assertTrue(
                $routes->contains(function ($route) use ($routeName) {
                    return $route->getName() === $routeName;
                })
            );
        }
    }
}
