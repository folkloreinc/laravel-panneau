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
        $this->panneau->setRoutes(config('panneau.route.paths'));
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

        $prefix = config('panneau.route.prefix');
        $routes = [];
        foreach (config('panneau.route.paths') as $action => $route) {
            $path = $route['path'];
            if (!empty($prefix)) {
                $path = '/'.$prefix.$path;
            }
            $routes['resource.'.$action] = $path;
        }
        $this->assertEquals($routes, $definition['routes']);
    }
}
