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
            \Folklore\EloquentJsonSchema\JsonSchemaServiceProvider::class,
            \Folklore\Panneau\PanneauServiceProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Panneau' => \Folklore\Panneau\Support\Facades\Panneau::class,
        ];
    }

    protected function withoutAuthentication()
    {
        $router = app('router');
        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('panneau.auth', \Authenticate::class);
        } else {
            $router->middleware('panneau.auth', \Authenticate::class);
        }
    }

    protected function callAsJson($method, $uri, $data = [])
    {
        $content = json_encode($data);
        $server = $this->transformHeadersToServerVars([
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]);
        return $this->call($method, $uri, [], [], [], $server, $content);
    }
}
