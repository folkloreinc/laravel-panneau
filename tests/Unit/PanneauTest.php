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
        $this->panneau->setDefaultRoutes(config('panneau.routes.defaultRoutes'));
        $this->panneau->setCustomRoutes(config('panneau.routes.customRoutes'));
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
        $routes = array_map(function ($route) {
            return preg_replace('/^panneau\./', '', $route);
        }, $this->panneau->getAllRoutes());
        $this->assertEquals($routes, array_keys($definition['routes']));
    }
}
