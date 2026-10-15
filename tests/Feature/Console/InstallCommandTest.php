<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Panneau\Support\Facade;
use Panneau\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $project;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // An empty project, so the command doesn't write into the Testbench skeleton
        $this->project = sys_get_temp_dir().'/panneau-install-'.bin2hex(random_bytes(4));
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->project.'/bootstrap');
        $files->ensureDirectoryExists($this->project.'/app');
        $files->put($this->project.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
        $files->put($this->project.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        $this->app->setBasePath($this->project);
        $this->app->useAppPath($this->project.'/app');
        $this->app->useConfigPath($this->project.'/config');
        $this->app->useBootstrapPath($this->project.'/bootstrap');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->project);

        parent::tearDown();
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->project.'/'.$path);
    }

    public function test_it_writes_the_project_files(): void
    {
        $this->artisan('panneau:install', ['--guard' => 'admin', '--login-url' => '/admin/login', '--resource' => ''])
            ->assertSuccessful();

        $config = require $this->project.'/config/panneau.php';
        $this->assertSame('admin', $config['auth']['guard']);
        $this->assertSame('/admin/login', $config['auth']['login_url']);
        $this->assertSame(['web', 'panneau.auth'], $config['middleware']);

        $provider = $this->read('app/Providers/PanneauServiceProvider.php');
        $this->assertStringContainsString('namespace App\\Providers;', $provider);
        $this->assertStringContainsString("Gate::define('viewPanneau'", $provider);
        $this->assertStringContainsString('return false;', $provider);
        $this->assertStringContainsString("base_path('routes/panneau.php')", $provider);

        $this->assertStringContainsString('Panneau::routes();', $this->read('routes/panneau.php'));
        $this->assertStringContainsString('App\\Providers\\PanneauServiceProvider::class', $this->read('bootstrap/providers.php'));
        $this->assertFileDoesNotExist($this->project.'/app/Panneau');

        $this->assertPhpFilesAreValid();
    }

    public function test_it_creates_a_first_resource_and_its_policy(): void
    {
        $this->artisan('panneau:install', ['--guard' => 'web', '--login-url' => '/login', '--resource' => 'TestApp\\Models\\Post'])
            ->assertSuccessful();

        $resource = $this->read('app/Panneau/Resources/PostsResource.php');
        $this->assertStringContainsString('class PostsResource extends Resource', $resource);
        $this->assertStringContainsString('use TestApp\\Models\\Post;', $resource);
        $this->assertStringContainsString('return Post::class;', $resource);
        $this->assertStringContainsString("Text::make('title')->required()", $resource);
        $this->assertStringContainsString("Textarea::make('body')", $resource);
        $this->assertStringContainsString("Boolean::make('featured')", $resource);
        $this->assertStringContainsString("DateTime::make('published_at')", $resource);
        $this->assertStringNotContainsString("'team_id'", $resource);
        $this->assertStringNotContainsString("'created_at'", $resource);
        $this->assertStringNotContainsString("'secret'", $resource);
        $this->assertStringContainsString("->columns(['title', 'status', 'published_at'])", $resource);
        $this->assertStringContainsString("->search(['title', 'status'])", $resource);

        $policy = $this->read('app/Policies/PostPolicy.php');
        $this->assertStringContainsString('class PostPolicy', $policy);
        $this->assertStringContainsString('public function update(Authenticatable $user, Post $post): bool', $policy);

        $this->assertStringContainsString('\\App\\Panneau\\Resources\\PostsResource::class,', $this->read('app/Providers/PanneauServiceProvider.php'));
        $this->assertPhpFilesAreValid();

        // The generated resource works
        require_once $this->project.'/app/Panneau/Resources/PostsResource.php';
        Facade::resources(['App\\Panneau\\Resources\\PostsResource']);
        $this->assertValidDefinition(Facade::definition());
    }

    public function test_it_refuses_a_class_that_is_not_a_model(): void
    {
        $this->artisan('panneau:install', ['--guard' => 'web', '--login-url' => '/login', '--resource' => 'stdClass'])
            ->assertFailed();
    }

    public function test_it_asks_when_options_are_missing(): void
    {
        $this->artisan('panneau:install')
            ->expectsQuestion('Guard of the admin users', 'web')
            ->expectsQuestion('Where are unauthenticated users sent?', '/login')
            ->expectsQuestion('Create a first resource for a model? (class name, empty to skip)', '')
            ->assertSuccessful();

        $this->assertFileExists($this->project.'/config/panneau.php');
    }

    public function test_existing_files_are_kept_without_force(): void
    {
        (new Filesystem)->ensureDirectoryExists($this->project.'/routes');
        file_put_contents($this->project.'/routes/panneau.php', '<?php // mine');
        $options = ['--guard' => 'web', '--login-url' => '/login', '--resource' => ''];

        $this->artisan('panneau:install', $options)->assertSuccessful();
        $this->assertSame('<?php // mine', $this->read('routes/panneau.php'));

        $this->artisan('panneau:install', [...$options, '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('Panneau::routes();', $this->read('routes/panneau.php'));
        $this->assertSame(1, substr_count($this->read('bootstrap/providers.php'), 'PanneauServiceProvider'));
    }

    /**
     * The package.json of the Laravel 12 skeleton.
     */
    private const PACKAGE_JSON = <<<'JSON'
        {
            "$schema": "https://www.schemastore.org/package.json",
            "private": true,
            "type": "module",
            "scripts": {
                "build": "vite build",
                "dev": "vite"
            },
            "devDependencies": {
                "@tailwindcss/vite": "^4.0.0",
                "laravel-vite-plugin": "^2.0.0",
                "tailwindcss": "^4.0.0",
                "vite": "^7.0.7"
            }
        }

        JSON;

    private const VITE_CONFIG = <<<'JS'
        import { defineConfig } from 'vite';
        import laravel from 'laravel-vite-plugin';

        export default defineConfig({
            plugins: [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.js'],
                    refresh: true,
                }),
            ],
        });

        JS;

    private const OPTIONS = ['--guard' => 'web', '--login-url' => '/login', '--resource' => ''];

    public function test_it_installs_the_js_entry(): void
    {
        file_put_contents($this->project.'/package.json', self::PACKAGE_JSON);
        file_put_contents($this->project.'/vite.config.js', self::VITE_CONFIG);

        $this->artisan('panneau:install', self::OPTIONS)
            ->expectsOutputToContain('Run npm install')
            ->assertSuccessful();

        $entry = $this->read('resources/js/panneau.tsx');
        $this->assertStringContainsString("import { createPanneauApp } from '@panneau/app';", $entry);
        $this->assertStringContainsString("element: document.getElementById('panneau')!,", $entry);

        $this->assertSame(<<<'JSON'
            {
                "$schema": "https://www.schemastore.org/package.json",
                "private": true,
                "type": "module",
                "scripts": {
                    "build": "vite build",
                    "dev": "vite"
                },
                "devDependencies": {
                    "@tailwindcss/vite": "^4.0.0",
                    "@vitejs/plugin-react": "^5.2.0",
                    "laravel-vite-plugin": "^2.0.0",
                    "tailwindcss": "^4.0.0",
                    "vite": "^7.0.7"
                },
                "dependencies": {
                    "@panneau/app": "^5.0.0-alpha.0",
                    "@tanstack/react-query": "^5.0.0",
                    "react": "^19.2.0",
                    "react-dom": "^19.2.0"
                }
            }

            JSON, $this->read('package.json'));

        $vite = $this->read('vite.config.js');
        $this->assertStringContainsString("import react from '@vitejs/plugin-react';", $vite);
        $this->assertStringContainsString("input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/panneau.tsx'],", $vite);
        $this->assertStringContainsString("        }),\n        react(),\n    ],", $vite);

        // A second run changes nothing
        $files = [$this->read('package.json'), $vite];
        $this->artisan('panneau:install', self::OPTIONS)->assertSuccessful();
        $this->assertSame($files, [$this->read('package.json'), $this->read('vite.config.js')]);
    }

    public function test_it_replaces_panneau_4_and_keeps_the_other_versions(): void
    {
        file_put_contents($this->project.'/package.json', json_encode([
            'dependencies' => ['@panneau/app' => '^4.1.0', 'react' => '^18.3.0', 'axios' => '^1.0.0'],
            'devDependencies' => ['@tanstack/react-query' => '^5.50.0', 'vite' => '^7.0.0'],
            'scripts' => (object) [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($this->project.'/package.json', str_replace('    ', '  ', $this->read('package.json')));

        $this->artisan('panneau:install', self::OPTIONS)
            ->expectsOutputToContain('package.json has react ^18.3.0: Panneau needs ^19.2.0.')
            ->assertSuccessful();

        $json = $this->read('package.json');
        $this->assertStringContainsString("\n  \"dependencies\": {\n    \"@panneau/app\": \"^5.0.0-alpha.0\",", $json);
        $this->assertStringContainsString('"scripts": {}', $json);
        $package = json_decode($json, true);
        $this->assertSame(['@panneau/app' => '^5.0.0-alpha.0', 'axios' => '^1.0.0', 'react' => '^18.3.0', 'react-dom' => '^19.2.0'], $package['dependencies']);
        $this->assertSame(['@tanstack/react-query' => '^5.50.0', '@vitejs/plugin-react' => '^5.2.0', 'vite' => '^7.0.0'], $package['devDependencies']);
    }

    public function test_it_tells_what_to_add_when_it_cannot(): void
    {
        $vite = "import config from './vite.shared.js';\n\nexport default config;\n";
        file_put_contents($this->project.'/vite.config.ts', $vite);

        $this->artisan('panneau:install', self::OPTIONS)
            ->expectsOutputToContain('No valid package.json: add @panneau/app@^5.0.0-alpha.0, @tanstack/react-query@^5.0.0, react@^19.2.0, react-dom@^19.2.0, @vitejs/plugin-react@^5.2.0.')
            ->expectsOutputToContain("vite.config.ts could not be updated: add 'resources/js/panneau.tsx' to the input of laravel() and the react() plugin of @vitejs/plugin-react.")
            ->assertSuccessful();

        $this->assertSame($vite, $this->read('vite.config.ts'));
        $this->assertFileDoesNotExist($this->project.'/package.json');
        $this->assertFileExists($this->project.'/resources/js/panneau.tsx');
    }

    public function test_the_js_can_be_skipped(): void
    {
        file_put_contents($this->project.'/package.json', self::PACKAGE_JSON);
        file_put_contents($this->project.'/vite.config.js', self::VITE_CONFIG);

        $this->artisan('panneau:install', [...self::OPTIONS, '--no-js' => true])
            ->doesntExpectOutputToContain('npm install')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->project.'/resources/js/panneau.tsx');
        $this->assertSame(self::PACKAGE_JSON, $this->read('package.json'));
        $this->assertSame(self::VITE_CONFIG, $this->read('vite.config.js'));
    }

    private function assertPhpFilesAreValid(): void
    {
        $files = (new Filesystem)->allFiles($this->project);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
