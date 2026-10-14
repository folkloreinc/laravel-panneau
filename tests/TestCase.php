<?php

namespace Panneau\Tests;

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

    public function setUp(): void
    {
        parent::setUp();

        app('panneau')->routes();

        // Routes registered after the app has booted need their name lookups refreshed
        app('router')->getRoutes()->refreshNameLookups();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Panneau\ServiceProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Panneau' => \Panneau\Support\Facade::class,
        ];
    }
}
