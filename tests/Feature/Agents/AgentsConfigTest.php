<?php

namespace Panneau\Tests\Feature\Agents;

use Laravel\Mcp\Server\McpServiceProvider;
use LogicException;
use Panneau\Agents\Tools\Describe;
use Panneau\Agents\Tools\GetItem;
use Panneau\ServiceProvider;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;

/**
 * The `agents` section of the project's config is merged deeply with the package's (#305).
 */
class AgentsConfigTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class];
    }

    /**
     * The package's provider, registered once the project's config is set.
     *
     * @param  array<string, mixed>  $agents
     */
    private function registerWith(array $agents): void
    {
        $this->app['config']->set('panneau', ['path' => 'admin', 'auth' => ['mode' => 'panneau'], 'agents' => $agents]);
        $this->app->register(ServiceProvider::class, true);
    }

    public function test_the_project_writes_only_what_it_changes(): void
    {
        $this->registerWith(['mcp' => ['enabled' => true, 'oauth' => ['access_token_lifetime' => 60]]]);
        $config = $this->app['config'];

        $this->assertSame('admin', $config->get('panneau.path'));
        $this->assertContains(Describe::class, $config->get('panneau.agents.tools'));
        $this->assertTrue($config->get('panneau.agents.mcp.enabled'));
        $this->assertSame('mcp', $config->get('panneau.agents.mcp.path'));
        $this->assertSame('sanctum', $config->get('panneau.agents.mcp.guard'));
        $this->assertFalse($config->get('panneau.agents.mcp.oauth.enabled'));
        $this->assertSame(60, $config->get('panneau.agents.mcp.oauth.access_token_lifetime'));
        $this->assertSame(60, $config->get('panneau.agents.rate_limit'));
        $this->assertSame(900, $config->get('panneau.agents.uploads.url_ttl'));
        // Outside `agents`, a section of the project is still its whole
        $this->assertSame(['mode' => 'panneau'], $config->get('panneau.auth'));
    }

    public function test_a_list_of_tools_is_the_projects_whole(): void
    {
        $this->registerWith(['tools' => [Describe::class, GetItem::class]]);

        $this->assertSame([Describe::class, GetItem::class], $this->app['config']->get('panneau.agents.tools'));
    }

    public function test_an_empty_list_of_tools_is_refused(): void
    {
        $this->registerWith(['tools' => [], 'mcp' => ['enabled' => true]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('panneau.agents.tools is empty');

        Panneau::mcpRoutes();
    }
}
