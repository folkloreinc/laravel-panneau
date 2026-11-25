<?php

namespace Panneau\Tests;

use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Panneau\ServiceProvider;
use Panneau\Support\Facade;
use Panneau\Tests\Concerns\ValidatesOpenApi;
use Symfony\Component\HttpFoundation\Response;

class TestCase extends BaseTestCase
{
    use ValidatesOpenApi;

    /**
     * Path of the API in the tests. Every response under it is checked against openapi.json.
     */
    protected const API = '/panneau/api';

    /**
     * The tests ignore the skeleton's .env: `testbench workbench:build`, which serves the
     * conformance app, writes one (with `APP_URL=http://localhost:8000`) that would change them.
     *
     * @var bool
     */
    protected $loadEnvironmentVariables = false;

    /**
     * Checks every response of the Panneau API against the protocol's OpenAPI document.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @param  string|null  $content
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        $path = (string) parse_url($uri, PHP_URL_PATH);
        // Requests that no route matched are not Panneau's
        if (str_starts_with($path, self::API.'/') && $response->baseRequest?->route() !== null) {
            $this->assertMatchesOpenApi($method, substr($path, strlen(self::API)), $response);
        }

        return $response;
    }

    /**
     * The rate limiters count in the cache: in memory, rather than in the skeleton's default
     * store, the database, which has no cache table. The tests use the in-memory database of
     * Testbench, even when the skeleton has the `database.sqlite` of the conformance app.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $config = $app['config'];
        $config->set('cache.default', 'array');
        if ($config->get('database.default') === 'sqlite' && $config->get('database.connections.sqlite.database') === $app->databasePath('database.sqlite')) {
            $config->set('database.default', 'testing');
        }
    }

    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Panneau' => Facade::class];
    }
}
