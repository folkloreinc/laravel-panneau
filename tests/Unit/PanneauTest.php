<?php

namespace Panneau\Tests\Unit;

use Panneau\Tests\TestCase;
use Panneau\Panneau;

/**
 * @coversDefaultClass Panneau\Panneau
 */
class PanneauTest extends TestCase
{
    protected $panneau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panneau = new Panneau(app(), app('auth'));
    }

    /**
     * Test getting Panneau's definition
     *
     * @test
     * @covers ::getDefinition
     *
     */
    public function testDefinition()
    {
        $resources = $this->panneau->resources();
        $definition = $this->panneau->definition()->toArray();

        // Check routes
        $resourceRoutes = [
            'resource.index',
            'resource.definition',
            'resource.create',
            'resource.store',
            'resource.show',
            'resource.edit',
            'resource.update',
            'resource.destroy',
            'resource.delete'
        ];
        $customResourceRoutes = $resources
            ->instances()
            ->reduce(function ($allRoutes, $resource) {
                $controller = $resource->getController();
                if (is_null($controller)) {
                    return $allRoutes;
                }
                $resourceName = $resource->getName();
                $names = $resource->getRoutes()->keys()->map(function ($action) use ($resourceName) {
                    return sprintf('resource.%s.%s', $resourceName, $action);
                })->all();
                return array_merge($allRoutes, $names);
            }, []);
        $routes = array_merge($resourceRoutes, $customResourceRoutes);
        $this->assertEquals($routes, array_keys($definition['routes']));
    }
}
