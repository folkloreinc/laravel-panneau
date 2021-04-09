<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Illuminate\Support\Collection;

class DefinitionTest extends TestCase
{
    protected $panneau;

    public function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
    }

    /**
     * Test definition resources
     *
     * @return void
     */
    public function testResources()
    {
        $this->panneau->resources([\TestApp\Resources\PagesResource::class]);

        $definition = $this->panneau->definition();

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(1, $resources->count());

        $this->panneau->resources([\TestApp\Resources\PagesResource::class]);

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(2, $resources->count());

        $this->assertContainsOnlyInstancesOf(\TestApp\Resources\PagesResource::class, $resources);
    }

    /**
     * Test definition routes
     *
     * @return void
     */
    public function testRoutes()
    {
        $this->panneau->resources([\TestApp\Resources\PagesResource::class]);

        $definition = $this->panneau->definition();

        $routes = $definition->routes();

        $this->assertInstanceOf(Collection::class, $routes);

        // Test that route key is the route name and that all routes start with the prefix
        foreach ($routes as $routeName => $route) {
            $this->assertEquals($routeName, $route->getName());
            $this->assertMatchesRegularExpression(
                '%' . $this->app['panneau.router']::PREFIX . '%',
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
