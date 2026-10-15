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
