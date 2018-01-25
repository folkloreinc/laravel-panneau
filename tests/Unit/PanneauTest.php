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
        $this->panneau->setDefaultRoutes(config('panneau.route.paths'));
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
        $defaultRoutes = array_map(function ($route) use ($prefix) {
            $path = $route['path'];
            if (!empty($prefix)) {
                $path = '/'.$prefix.$path;
            }
            $route['path'] = $path;
            return $route;
        }, config('panneau.route.paths'));
        $this->assertEquals($defaultRoutes, $definition['defaultRoutes']);
    }
}
