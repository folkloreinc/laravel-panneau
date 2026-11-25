<?php

namespace Panneau\Tests\Unit;

use Panneau\Tests\TestCase;

/**
 * The tests ignore the .env and the database.sqlite of Testbench's skeleton, which
 * `testbench workbench:build` makes when the conformance app is served.
 */
class TestEnvironmentTest extends TestCase
{
    private string $environmentFile;

    private ?string $previousEnvironment = null;

    private string $databaseFile;

    private bool $hadDatabase = false;

    protected function setUp(): void
    {
        $this->environmentFile = static::applicationBasePath().'/.env';
        if (is_file($this->environmentFile)) {
            $this->previousEnvironment = (string) file_get_contents($this->environmentFile);
        }
        file_put_contents($this->environmentFile, "APP_URL=http://skeleton.invalid\nSESSION_DRIVER=cookie\n");
        $this->databaseFile = static::applicationBasePath().'/database/database.sqlite';
        $this->hadDatabase = is_file($this->databaseFile);
        if (! $this->hadDatabase) {
            touch($this->databaseFile);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousEnvironment !== null) {
            file_put_contents($this->environmentFile, $this->previousEnvironment);
        } else {
            @unlink($this->environmentFile);
        }
        if (! $this->hadDatabase) {
            @unlink($this->databaseFile);
        }
    }

    public function test_the_skeletons_env_is_not_loaded(): void
    {
        $this->assertSame('http://localhost', $this->app['config']->get('app.url'));
        $this->assertNotSame('cookie', $this->app['config']->get('session.driver'));
    }

    public function test_the_skeletons_database_is_not_used(): void
    {
        $this->assertSame('testing', $this->app['config']->get('database.default'));
        $this->assertSame(':memory:', $this->app['db']->connection()->getDatabaseName());
    }
}
