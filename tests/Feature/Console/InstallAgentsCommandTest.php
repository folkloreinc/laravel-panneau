<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Foundation\Auth\User as PlainUser;
use Panneau\Console\Packages;
use Panneau\Tests\Concerns\InstallsInAProject;
use Panneau\Tests\Feature\Agents\McpTestCase;
use TestApp\Models\User;

/**
 * `panneau:install --agents` with Sanctum's personal tokens (#305).
 */
class InstallAgentsCommandTest extends McpTestCase
{
    use InstallsInAProject;

    protected function defineEnvironment($app): void
    {
        $this->makeProject($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_it_turns_on_the_server_and_registers_its_routes(): void
    {
        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('personal tokens and laravel/sanctum')
            ->expectsOutputToContain('The MCP server is at http://localhost/panneau/mcp')
            ->expectsOutputToContain('php artisan panneau:agent-token')
            ->assertSuccessful();

        $config = $this->projectConfig('panneau');
        $this->assertTrue($config['agents']['mcp']['enabled']);
        $this->assertSame('sanctum', $config['agents']['mcp']['guard']);
        $this->assertFalse($config['agents']['mcp']['oauth']['enabled']);
        $this->assertCount(6, $config['agents']['tools']);

        $routes = $this->read('routes/panneau.php');
        $this->assertMatchesRegularExpression('/Panneau::routes\(\);\n\n\/\/ [^\n]+\nPanneau::mcpRoutes\(\);/', $routes);
        $this->assertNotFalse(exec('php -l '.escapeshellarg($this->project.'/routes/panneau.php')));
        $this->assertSame([], $this->migrations(), 'Sanctum\'s table exists: nothing to publish.');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])->assertSuccessful();
        $config = $this->read('config/panneau.php');
        $routes = $this->read('routes/panneau.php');

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertSame($config, $this->read('config/panneau.php'));
        $this->assertSame($routes, $this->read('routes/panneau.php'));
    }

    public function test_a_short_config_gets_only_what_it_lacks(): void
    {
        file_put_contents($this->project.'/config/panneau.php', "<?php\n\nreturn [\n    'path' => 'admin',\n];\n");
        $this->app['config']->set('panneau.agents.mcp.enabled', false);

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertSame(['path' => 'admin', 'agents' => ['mcp' => ['enabled' => true]]], $this->projectConfig('panneau'));
    }

    public function test_a_value_the_config_computes_is_left_to_the_project(): void
    {
        $code = str_replace(
            "registers nothing.\n            'enabled' => false,",
            "registers nothing.\n            'enabled' => env('PANNEAU_AGENTS_MCP', false),",
            $this->read('config/panneau.php'),
        );
        file_put_contents($this->project.'/config/panneau.php', $code);
        $this->app['config']->set('panneau.agents.mcp.enabled', false);

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Set panneau.agents.mcp.enabled to true: config/panneau.php computes it')
            ->assertSuccessful();

        $this->assertStringContainsString("env('PANNEAU_AGENTS_MCP', false)", $this->read('config/panneau.php'));
    }

    public function test_it_publishes_the_table_of_sanctum_when_it_is_missing(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->drop('personal_access_tokens');

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Run the migrations: php artisan migrate')
            ->assertSuccessful();

        $this->assertCount(1, array_filter($this->migrations(), fn (string $file): bool => str_ends_with($file, '_create_personal_access_tokens_table.php')));
    }

    public function test_it_says_when_the_model_has_no_tokens(): void
    {
        $this->app['config']->set('auth.providers.users.model', PlainUser::class);

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Add the HasApiTokens of laravel/sanctum to Illuminate\Foundation\Auth\User')
            ->assertSuccessful();
    }

    public function test_oauth_is_refused_on_a_model_of_sanctum(): void
    {
        $this->artisan('panneau:install', ['--agents' => true, '--oauth' => true, '--no-interaction' => true])
            ->expectsOutputToContain('uses the HasApiTokens of laravel/sanctum')
            ->assertFailed();

        $this->assertFalse($this->projectConfig('panneau')['agents']['mcp']['enabled']);
    }

    public function test_without_laravel_mcp_it_says_what_to_require(): void
    {
        $this->app->instance(Packages::class, new class extends Packages
        {
            public function installed(string $package): bool
            {
                return $package !== 'laravel/mcp';
            }
        });

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Run composer require laravel/mcp, then php artisan panneau:install --agents again.')
            ->assertFailed();

        $this->assertFalse($this->projectConfig('panneau')['agents']['mcp']['enabled']);
    }

    public function test_it_needs_the_config_of_panneau(): void
    {
        unlink($this->project.'/config/panneau.php');

        $this->artisan('panneau:install', ['--agents' => true, '--no-interaction' => true])
            ->expectsOutputToContain('run php artisan panneau:install first')
            ->assertFailed();
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        return array_map('basename', glob($this->project.'/database/migrations/*.php') ?: []);
    }
}
