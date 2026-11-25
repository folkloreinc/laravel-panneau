<?php

namespace Panneau\Tests\Concerns;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;

/**
 * A project of its own for the installers, so they don't write into the Testbench skeleton:
 * made before the providers boot, which keep its paths for their publications.
 */
trait InstallsInAProject
{
    protected string $project;

    protected function makeProject(Application $app): void
    {
        $this->project = sys_get_temp_dir().'/panneau-agents-'.bin2hex(random_bytes(4));
        $files = new Filesystem;
        foreach (['bootstrap', 'app', 'config', 'routes', 'database/migrations', 'storage'] as $directory) {
            $files->ensureDirectoryExists($this->project.'/'.$directory);
        }
        $files->copy(__DIR__.'/../../src/config/panneau.php', $this->project.'/config/panneau.php');
        $files->copy(__DIR__.'/../../src/stubs/routes.stub', $this->project.'/routes/panneau.php');
        $files->put($this->project.'/config/auth.php', "<?php\n\nreturn [\n    'guards' => [\n        'web' => [\n            'driver' => 'session',\n            'provider' => 'users',\n        ],\n    ],\n];\n");
        // The skeleton's migrations, for #[WithMigration]: a link, which deleting the project keeps
        symlink($app->basePath('migrations'), $this->project.'/migrations');
        $app->setBasePath($this->project);
        $app->useConfigPath($this->project.'/config');
        $app->useDatabasePath($this->project.'/database');
        $app->useStoragePath($this->project.'/storage');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->project);

        parent::tearDown();
    }

    protected function read(string $path): string
    {
        return (string) file_get_contents($this->project.'/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectConfig(string $name): array
    {
        return require $this->project.'/config/'.$name.'.php';
    }
}
