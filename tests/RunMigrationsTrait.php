<?php

trait RunMigrationsTrait
{
    public function runMigrations()
    {
        $migrator = $this->getMigrator();

        if (!$migrator->repositoryExists()) {
            $this->artisan('migrate:install', [
                '--database' => 'testbench'
            ]);
        }

        $migrator->run($this->getMigrationPaths());

        $this->beforeApplicationDestroyed(function () {
            $this->rollbackMigrations();
        });
    }

    public function rollbackMigrations()
    {
        $migrator = $this->getMigrator();
        $migrator->rollback($this->getMigrationPaths());
    }

    protected function getMigrator()
    {
        $migrator = $this->app['migrator'];
        $migrator->setConnection('testbench');
        return $migrator;
    }

    protected function getMigrationPaths()
    {
        return [
            realpath(__DIR__.'/../src/migrations'),
            realpath(__DIR__.'/../vendor/folklore/laravel-mediatheque/src/migrations')
        ];
    }
}
