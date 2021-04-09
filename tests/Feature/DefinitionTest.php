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

        $routesToTest = [
            0 => 'panneau.resources.index',
            1 => 'panneau.resources.create',
            2 => 'panneau.resources.store',
            3 => 'panneau.resources.show',
            4 => 'panneau.resources.edit',
            5 => 'panneau.resources.update',
            6 => 'panneau.resources.destroy',
            7 => 'panneau.resources.delete',
        ];

        $routes = $definition->routes();

        $this->assertInstanceOf(Collection::class, $routes);

        $index = 0;
        foreach ($routes as $routeName => $route) {
            $this->assertMatchesRegularExpression(
                '%' . $this->app['panneau.router']::PREFIX . '%',
                $routeName
            );
            $this->assertEquals($routesToTest[$index], $routeName);
            $index++;
        }
    }
}
