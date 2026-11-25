<?php

namespace Panneau\Tests\Feature\Console;

use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use LogicException;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Agents\Connections;
use Panneau\Tests\Feature\Agents\OAuthTestCase;
use TestApp\Models\OAuthUser;
use TestApp\Models\User;

/**
 * `panneau:agent-token` with Passport (#300): what is missing is said, with the command that fixes
 * it, and a token the MCP server would refuse isn't made.
 */
#[WithMigration]
class AgentTokenCommandWithPassportTest extends OAuthTestCase
{
    public function test_without_a_personal_access_client_it_says_how_to_create_one(): void
    {
        $this->createOAuthUser();

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain('php artisan passport:client --personal --provider=users')
            ->assertFailed();
        $this->assertSame(0, Token::query()->count());

        $this->app->make(ClientRepository::class)->createPersonalAccessGrantClient('Personal', 'users');
        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])->assertSuccessful();
        $this->assertSame(['mcp:use'], Token::query()->sole()->scopes);
    }

    public function test_a_model_of_sanctum_makes_no_token_for_a_passport_guard(): void
    {
        $this->app->make(ClientRepository::class)->createPersonalAccessGrantClient('Personal', 'users');
        $user = $this->createUser();
        config(['auth.providers.users.model' => User::class]);

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain('uses the HasApiTokens of laravel/sanctum, but panneau.agents.mcp.guard "api" is a passport guard')
            ->assertFailed();

        $connections = $this->app->make(Connections::class);
        $this->assertFalse($connections->canCreateTokens($user));
        $this->expectException(LogicException::class);
        $connections->createToken($user, 'Claude');
    }

    public function test_a_model_of_passport_makes_no_token_for_a_sanctum_guard(): void
    {
        $this->app->make(ClientRepository::class)->createPersonalAccessGrantClient('Personal', 'users');
        $user = $this->createOAuthUser();
        config(['panneau.agents.mcp.guard' => 'sanctum']);

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain(OAuthUser::class.' uses the HasApiTokens of laravel/passport, but panneau.agents.mcp.guard "sanctum" is a sanctum guard')
            ->assertFailed();
        $this->assertFalse($this->app->make(Connections::class)->canCreateTokens($user));
        $this->assertSame(0, Token::query()->count());
    }
}
