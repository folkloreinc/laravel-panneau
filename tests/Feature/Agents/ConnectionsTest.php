<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\User;

/**
 * The connections of the account with Sanctum's personal tokens (#288), and the `agents` key of
 * the definition.
 */
#[WithMigration]
class ConnectionsTest extends McpTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('panneau.auth.mode', 'panneau');
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['panneau.auth.password_confirmed_at' => time()]);
    }

    public function test_it_lists_the_agent_tokens_of_the_user_only(): void
    {
        $user = $this->createUser();
        Carbon::setTestNow('2026-11-24 10:00:00');
        $reader = $user->createToken('Reader', ['mcp:use']);
        Carbon::setTestNow('2026-11-24 11:00:00');
        $writer = $user->createToken('Writer', ['mcp:use', 'panneau:write'], Carbon::parse('2026-12-24 11:00:00'));
        $writer->accessToken->forceFill(['last_used_at' => Carbon::parse('2026-11-24 11:30:00')])->save();
        // The project's other tokens, an expired one, and those of another user
        $user->createToken('API', ['*']);
        $user->createToken('Reports', ['reports:read']);
        $user->createToken('Expired', ['mcp:use'], Carbon::parse('2026-11-24 10:30:00'));
        $this->createUser('grace@example.com')->createToken('Grace', ['mcp:use']);
        Carbon::setTestNow('2026-11-24 12:00:00');
        $this->signIn($user);

        $this->getJson('/panneau/api/account/connections')
            ->assertOk()
            ->assertExactJson(['data' => [
                [
                    'id' => 'token:'.$writer->accessToken->getKey(),
                    'type' => 'token',
                    'name' => 'Writer',
                    'write' => true,
                    'createdAt' => '2026-11-24T11:00:00+00:00',
                    'lastUsedAt' => '2026-11-24T11:30:00+00:00',
                    'expiresAt' => '2026-12-24T11:00:00+00:00',
                ],
                [
                    'id' => 'token:'.$reader->accessToken->getKey(),
                    'type' => 'token',
                    'name' => 'Reader',
                    'write' => false,
                    'createdAt' => '2026-11-24T10:00:00+00:00',
                    'lastUsedAt' => null,
                    'expiresAt' => null,
                ],
            ]]);
    }

    public function test_sanctums_own_expiration_ends_a_connection(): void
    {
        $user = $this->createUser();
        $this->app['config']->set('sanctum.expiration', 60);
        Carbon::setTestNow('2026-11-24 10:00:00');
        $user->createToken('Old', ['mcp:use']);
        Carbon::setTestNow('2026-11-24 10:30:00');
        $user->createToken('New', ['mcp:use']);
        Carbon::setTestNow('2026-11-24 11:15:00');
        $this->signIn($user);

        $this->getJson('/panneau/api/account/connections')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['New'])
            ->assertJsonPath('data.0.expiresAt', '2026-11-24T11:30:00+00:00');
    }

    public function test_a_new_token_reads_on_the_mcp_server_and_writes_with_write(): void
    {
        $user = $this->createUser();
        $this->signIn($user);

        $created = $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Claude'])
            ->assertCreated()
            ->assertJsonPath('connection.type', 'token')
            ->assertJsonPath('connection.name', 'Claude')
            ->assertJsonPath('connection.write', false)
            ->assertJsonPath('connection.expiresAt', null);
        $this->assertSame(['mcp:use'], PersonalAccessToken::query()->sole()->abilities);
        $this->rpc((string) $created->json('token'), 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item']);

        $this->signIn($user);
        $writer = $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Cursor', 'write' => true])
            ->assertCreated()
            ->assertJsonPath('connection.write', true);
        $this->rpc((string) $writer->json('token'), 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item', 'save_item', 'run_action', 'upload_file']);

        $this->signIn($user);
        $this->getJson('/panneau/api/account/connections')->assertJsonPath('data.*.id', [
            $writer->json('connection.id'),
            $created->json('connection.id'),
        ]);
    }

    public function test_a_new_token_expires_after_the_lifetime_of_the_config(): void
    {
        $this->app['config']->set('panneau.agents.mcp.token_lifetime', 7);
        Carbon::setTestNow('2026-11-24 10:00:00');
        $user = $this->createUser();
        $this->signIn($user);

        $token = $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Claude'])
            ->assertCreated()
            ->assertJsonPath('connection.expiresAt', '2026-12-01T10:00:00+00:00')
            ->json('token');
        $this->assertSame('2026-12-01 10:00:00', PersonalAccessToken::query()->sole()->expires_at?->toDateTimeString());

        Carbon::setTestNow('2026-12-01 10:00:01');
        $this->rpc((string) $token, 'tools/list')->assertUnauthorized();
    }

    public function test_a_new_token_needs_a_name_and_a_recent_password(): void
    {
        $user = $this->createUser();
        $this->signIn($user);
        $this->postJson('/panneau/api/account/connections/tokens', ['write' => 'yes'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'write']]);

        $this->flushSession();
        $this->actingAs($user);
        $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Claude'])
            ->assertStatus(423)
            ->assertJsonPath('type', Problem::PASSWORD_CONFIRMATION);
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_revoking_a_token_stops_it(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('Claude', ['mcp:use']);
        $this->rpc($token->plainTextToken, 'tools/list')->assertOk();

        $this->actingAs($user);
        $this->deleteJson('/panneau/api/account/connections/token:'.$token->accessToken->getKey())->assertStatus(423);

        $this->signIn($user);
        $this->deleteJson('/panneau/api/account/connections/token:'.$token->accessToken->getKey())->assertNoContent();
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->rpc($token->plainTextToken, 'tools/list')->assertUnauthorized();
    }

    public function test_the_connections_of_others_and_other_tokens_are_not_found(): void
    {
        $user = $this->createUser();
        $api = $user->createToken('API', ['*']);
        $other = $this->createUser('grace@example.com')->createToken('Grace', ['mcp:use']);
        $this->signIn($user);

        foreach (['token:'.$other->accessToken->getKey(), 'token:'.$api->accessToken->getKey(), (string) $other->accessToken->getKey(), 'client:1', 'nothing'] as $id) {
            $this->deleteJson('/panneau/api/account/connections/'.$id)->assertNotFound();
        }
        $this->assertSame(2, PersonalAccessToken::query()->count());
    }

    public function test_a_user_the_gate_of_the_agents_refuses_has_no_connections(): void
    {
        Gate::define('usePanneauAgents', fn (User $user): bool => $user->email !== 'eve@example.com');
        $this->signIn($this->createUser('eve@example.com'));

        $this->getJson('/panneau/api/account/connections')->assertForbidden();
        $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Claude'])->assertForbidden();
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('agents');

        $this->signIn($this->createUser('ada@example.com'));
        $this->getJson('/panneau/api/account/connections')->assertOk();
    }

    public function test_without_the_mcp_server_there_are_no_routes(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.agents.mcp.enabled', false);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->signIn($this->createUser());

        $this->assertNull($this->app['router']->getRoutes()->getByName('panneau.api.account.connections.tokens.store'));
        $this->getJson('/panneau/api/account/connections')->assertNotFound();
        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('agents');
    }

    public function test_the_definition_gives_the_url_of_the_mcp_server(): void
    {
        $this->signIn($this->createUser());

        $on = $this->getJson('/panneau/api/definition')
            ->assertOk()
            ->assertJsonPath('agents', ['mcp' => ['url' => 'http://localhost/panneau/mcp', 'tokens' => true]]);

        // The ETag follows the content
        $this->app['config']->set('panneau.agents.mcp.enabled', false);
        $off = $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('agents');
        $this->assertNotSame($on->headers->get('ETag'), $off->headers->get('ETag'));
    }

    public function test_without_a_model_that_makes_tokens_none_is_offered(): void
    {
        $this->signIn($this->createUser());
        $this->app['config']->set('auth.providers.users.model', NoTokensUser::class);
        $this->actingAs(NoTokensUser::query()->firstOrFail())->withSession(['panneau.auth.password_confirmed_at' => time()]);

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('agents.mcp.tokens', false);
        $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Claude'])
            ->assertStatus(409)
            ->assertJsonPath('detail', 'This account can\'t make personal tokens.');
    }

    public function test_the_definition_has_no_agents_without_the_route(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->signIn($this->createUser());

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('agents');
    }
}

/**
 * A user whose model can't make tokens.
 */
class NoTokensUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';
}
