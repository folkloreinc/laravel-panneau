<?php

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Path
        $app->instance('path.app', __DIR__.'/fixture');

        // Database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    public function setUp()
    {
        parent::setUp();

        app('panneau')->routes();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Panneau\PanneauServiceProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Panneau' => \Panneau\Support\Facade::class,
        ];
    }
}
