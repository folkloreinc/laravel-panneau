<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Token;
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
        $this->assertNull($token->expires_at);
        $this->assertMatchesRegularExpression('/Expires\W.*never/', $output);
    }

    public function test_days_set_an_expiry_that_sanctum_enforces(): void
    {
        $this->createUser();
        Carbon::setTestNow('2026-11-24 10:00:00');

        $this->assertSame(0, Artisan::call('panneau:agent-token', ['user' => 'ada@example.com', '--days' => '30']));
        $output = Artisan::output();
        $this->assertStringContainsString('2026-12-24 10:00:00', $output);
        $this->assertSame('2026-12-24 10:00:00', PersonalAccessToken::query()->sole()->expires_at?->toDateTimeString());

        preg_match('/^\d+\|\S+$/m', $output, $match);
        $this->travelTo('2026-12-24 09:59:00');
        $this->rpc($match[0], 'tools/list')->assertOk();
        $this->travelTo('2026-12-24 10:01:00');
        $this->rpc($match[0], 'tools/list')->assertUnauthorized();
    }

    public function test_the_config_gives_the_default_lifetime_and_zero_is_never(): void
    {
        $this->createUser();
        config(['panneau.agents.mcp.token_lifetime' => 7]);

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])->assertSuccessful();
        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com', '--days' => '0'])->assertSuccessful();

        [$first, $second] = PersonalAccessToken::query()->orderBy('id')->get()->all();
        $this->assertEqualsWithDelta(Carbon::now()->addDays(7)->getTimestamp(), $first->expires_at?->getTimestamp(), 5);
        $this->assertNull($second->expires_at);
    }

    public function test_a_lifetime_that_is_not_a_number_of_days_fails(): void
    {
        $this->createUser();

        foreach (['-1', 'abc', '1.5'] as $days) {
            $this->artisan('panneau:agent-token', ['user' => 'ada@example.com', '--days' => $days])
                ->expectsOutputToContain('is a number of days')
                ->assertFailed();
        }
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_with_passport_a_lifetime_fails_and_none_works(): void
    {
        $this->createUser();
        config(['auth.providers.users.model' => PassportUser::class]);

        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com', '--days' => '30'])
            ->expectsOutputToContain('Passport::personalAccessTokensExpireIn()')
            ->assertFailed();
        $this->artisan('panneau:agent-token', ['user' => 'ada@example.com'])
            ->expectsOutputToContain('passport-token')
            ->assertSuccessful();
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

/**
 * A user whose createToken() is Passport's: no expiry, and a PersonalAccessTokenResult.
 */
class PassportUser extends Authenticatable
{
    protected $table = 'users';

    /**
     * @param  array<int, string>  $scopes
     */
    public function createToken(string $name, array $scopes = []): object
    {
        // As Passport's PersonalAccessTokenResult: the JWT, and the stored token
        return new class($name, $scopes)
        {
            public string $accessToken = 'passport-token';

            /**
             * @param  array<int, string>  $scopes
             */
            public function __construct(private readonly string $name, private readonly array $scopes) {}

            public function getToken(): Token
            {
                return (new Token)->forceFill(['id' => 'a-token', 'name' => $this->name, 'scopes' => $this->scopes, 'created_at' => now()]);
            }
        };
    }
}
