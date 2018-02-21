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
        $definition = $this->panneau->getDefinition()->toArray();
        $definitionRoutes = array_map(function ($route) {
            return preg_replace('/^panneau\./', '', $route);
        }, config('panneau.definition.routes'));
        $routes = array_merge($definitionRoutes, [
            'resource.index',
            'resource.create',
            'resource.store',
            'resource.show',
            'resource.edit',
            'resource.update',
            'resource.destroy',
        ]);
        $this->assertEquals($routes, array_keys($definition['routes']));
    }
}
