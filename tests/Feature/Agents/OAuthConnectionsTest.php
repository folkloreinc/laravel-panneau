<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Orchestra\Testbench\Attributes\WithMigration;
use TestApp\Models\OAuthUser;

/**
 * The connections of the account with Passport (#288): one per OAuth client the user
 * authorized, and Passport's personal access tokens.
 */
#[WithMigration]
class OAuthConnectionsTest extends OAuthTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('panneau.auth.mode', 'panneau');
    }

    private function signIn(OAuthUser $user): void
    {
        $this->actingAs($user, 'web')->withSession(['panneau.auth.password_confirmed_at' => time()]);
    }

    /**
     * The tokens of an authorization of a client by the user.
     *
     * @return array<string, mixed>
     */
    private function authorize(OAuthUser $user, string $client, string $scope): array
    {
        $verifier = str_repeat('v', 64);
        $this->actingAs($user, 'web');
        $this->get('/oauth/authorize?'.http_build_query($this->authorization($client, $scope, $verifier)))->assertOk();
        $location = (string) $this->post('/oauth/authorize', $this->consent($client))->assertRedirect()->headers->get('Location');

        return $this->exchange($client, $location, $verifier);
    }

    public function test_it_lists_one_connection_per_client_of_the_user(): void
    {
        $user = $this->createOAuthUser();
        $claude = $this->registerClient('Claude');
        $this->authorize($user, $claude, 'mcp:use');
        $this->authorize($user, $claude, 'mcp:use panneau:write');
        $cursor = $this->registerClient('Cursor');
        $this->authorize($user, $cursor, 'mcp:use');
        // Another user's authorization, and a revoked one
        $this->authorize($this->createOAuthUser('grace@example.com'), $this->registerClient('Grace'), 'mcp:use');
        $old = $this->registerClient('Old');
        $this->authorize($user, $old, 'mcp:use');
        Token::query()->where('client_id', $old)->update(['revoked' => true]);
        $this->signIn($user);

        $connections = $this->getJson('/panneau/api/account/connections')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['client:'.$claude, 'client:'.$cursor], array_column($connections, 'id'));
        $byId = array_column($connections, null, 'id');
        $this->assertSame(['type' => 'client', 'name' => 'Claude', 'write' => true, 'lastUsedAt' => null], array_intersect_key($byId['client:'.$claude], array_flip(['type', 'name', 'write', 'lastUsedAt'])));
        $this->assertFalse($byId['client:'.$cursor]['write']);
        // Until its refresh token expires
        $refresh = Carbon::parse(DB::table('oauth_refresh_tokens')->max('expires_at'));
        $this->assertEqualsWithDelta($refresh->getTimestamp(), Carbon::parse($byId['client:'.$cursor]['expiresAt'])->getTimestamp(), 5);
    }

    public function test_revoking_a_client_stops_all_its_tokens(): void
    {
        $user = $this->createOAuthUser();
        $client = $this->registerClient('Claude');
        $first = $this->authorize($user, $client, 'mcp:use');
        $second = $this->authorize($user, $client, 'mcp:use panneau:write');
        $other = $this->authorize($this->createOAuthUser('grace@example.com'), $client, 'mcp:use');
        $this->rpc($first['access_token'], 'tools/list')->assertOk();

        $this->signIn($user);
        $this->deleteJson('/panneau/api/account/connections/client:'.$client)->assertNoContent();

        $this->rpc($first['access_token'], 'tools/list')->assertUnauthorized();
        $this->rpc($second['access_token'], 'tools/list')->assertUnauthorized();
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client, 'refresh_token' => $second['refresh_token']])->assertStatus(400);
        // Another user of the same client stays connected
        $this->rpc($other['access_token'], 'tools/list')->assertOk();

        $this->signIn($user);
        $this->getJson('/panneau/api/account/connections')->assertOk()->assertJsonPath('data', []);
        $this->deleteJson('/panneau/api/account/connections/client:'.$client)->assertNotFound();
    }

    public function test_the_clients_of_others_are_not_found(): void
    {
        $client = $this->registerClient('Grace');
        $this->authorize($this->createOAuthUser('grace@example.com'), $client, 'mcp:use');
        $this->signIn($this->createOAuthUser());

        $this->deleteJson('/panneau/api/account/connections/client:'.$client)->assertNotFound();
        $this->assertSame(0, Token::query()->where('revoked', true)->count());
    }

    public function test_personal_access_tokens_of_passport_are_connections(): void
    {
        $this->app->make(ClientRepository::class)->createPersonalAccessGrantClient('Personal', 'users');
        $user = $this->createOAuthUser();
        $user->createToken('API', ['*']);
        $this->signIn($user);

        $created = $this->postJson('/panneau/api/account/connections/tokens', ['name' => 'Desktop', 'write' => true])
            ->assertCreated()
            ->assertJsonPath('connection.type', 'token')
            ->assertJsonPath('connection.name', 'Desktop')
            ->assertJsonPath('connection.write', true);
        $this->rpc((string) $created->json('token'), 'tools/list')
            ->assertOk()
            ->assertJsonPath('result.tools.*.name', ['describe', 'find_items', 'get_item', 'save_item', 'run_action']);

        $this->signIn($user);
        $this->getJson('/panneau/api/account/connections')->assertOk()->assertJsonPath('data.*.id', [$created->json('connection.id')]);
        $this->deleteJson('/panneau/api/account/connections/'.$created->json('connection.id'))->assertNoContent();
        $this->rpc((string) $created->json('token'), 'tools/list')->assertUnauthorized();
    }

    public function test_the_definition_gives_the_url_of_the_mcp_server(): void
    {
        $this->signIn($this->createOAuthUser());

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('agents.mcp.url', 'http://localhost/panneau/mcp');
    }
}
