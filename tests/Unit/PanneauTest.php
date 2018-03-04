<?php

use Folklore\Panneau\Panneau;

/**
 * @coversDefaultClass Folklore\Panneau\Panneau
 */
class PanneauTest extends TestCase
{
    protected $panneau;

    public function setUp()
    {
        parent::setUp();

        $this->panneau = new Panneau(app());
        $this->panneau->setResources(config('panneau.resources'));
        $this->panneau->setDefinitionRoutes(config('panneau.definition.routes'));
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
        $resources = $this->panneau->getResources();
        $definition = $this->panneau->definition()->toArray();

        // Check routes
        $definitionRoutes = array_map(function ($route) {
            return preg_replace('/^panneau\./', '', $route);
        }, config('panneau.definition.routes'));
        $resourceRoutes = [
            'resource.index',
            'resource.definition',
            'resource.create',
            'resource.store',
            'resource.show',
            'resource.edit',
            'resource.update',
            'resource.destroy',
            'resource.delete',
        ];
        $customResourceRoutes = array_reduce($resources, function ($allRoutes, $resource) {
            $data = $resource->toArray();
            $routes = array_get($data, 'routes', []);
            $names = array_map(function ($name) use ($resource) {
                return 'resource.'.$resource->getId().'.'.$name;
            }, array_keys($routes));
            return array_merge($allRoutes, $names);
        }, []);
        $routes = array_merge($definitionRoutes, $resourceRoutes, $customResourceRoutes);
        $this->assertEquals($routes, array_keys($definition['routes']));
    }
}
