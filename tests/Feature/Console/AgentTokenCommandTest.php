<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Context\ContextParam;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Agents\McpTestCase;

/**
 * `panneau:agent-token`: a personal token for the MCP server, made on the command line.
 */
#[WithMigration]
class AgentTokenCommandTest extends McpTestCase
{
    public function test_it_creates_a_token_that_reads(): void
    {
        $this->createUser();

        $this->assertSame(0, Artisan::call('panneau:agent-token', ['user' => 'ada@example.com']));
        $output = Artisan::output();
        $this->assertStringContainsString('/panneau/mcp', $output);
        $this->assertStringNotContainsString('Context', $output);

        $this->assertMatchesRegularExpression('/^\d+\|\S+$/m', $output);
        preg_match('/^\d+\|\S+$/m', $output, $match);
        $this->rpc($match[0], 'tools/list')->assertOk();

        $token = PersonalAccessToken::query()->sole();
        $this->assertSame('Panneau MCP', $token->name);
        $this->assertSame(['mcp:use'], $token->abilities);
    }

    public function test_write_adds_the_ability_and_the_name_is_an_option(): void
    {
        $user = $this->createUser();

        $this->artisan('panneau:agent-token', ['user' => (string) $user->getKey(), '--write' => true, '--name' => 'Claude'])->assertSuccessful();

        $token = PersonalAccessToken::query()->sole();
        $this->assertSame('Claude', $token->name);
        $this->assertSame(['mcp:use', 'panneau:write'], $token->abilities);
    }

    public function test_with_a_context_it_says_the_url_is_followed_by_it(): void
    {
        $this->createUser();
        Panneau::context([ContextParam::make('client')->options(['4' => 'Acme']), ContextParam::make('season')->options(['2026' => '2026'])]);

        $this->assertSame(0, Artisan::call('panneau:agent-token', ['user' => 'ada@example.com']));

        $this->assertStringContainsString('/panneau/mcp/client/{client}/season/{season}', Artisan::output());
    }

    public function test_an_unknown_user_fails(): void
    {
        $this->artisan('panneau:agent-token', ['user' => 'nobody@example.com'])
            ->expectsOutputToContain('No user "nobody@example.com"')
            ->assertFailed();
    }
}
