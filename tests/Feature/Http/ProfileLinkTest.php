<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\User;
use TestApp\Panneau\Resources\UsersResource;
use TestApp\Policies\UserPolicy;

/**
 * The link to the user's profile in the `external` mode: their item in the resource `users`,
 * which the definition names in `user.resource` only when the user can open it (#202).
 */
#[WithMigration]
class ProfileLinkTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        UserPolicy::$denied = [];
        Gate::policy(User::class, UserPolicy::class);
    }

    protected function tearDown(): void
    {
        UserPolicy::$denied = [];

        parent::tearDown();
    }

    private function user(string $email): User
    {
        Panneau::resources([UsersResource::class]);

        return User::query()->create(['name' => ucfirst(strstr($email, '@', true) ?: $email), 'email' => $email, 'password' => 'secret']);
    }

    /**
     * `user.resource` is `null`, not left out: without it, the app would look for the resource
     * `users`.
     *
     * @param  array<string, mixed>  $user
     */
    private function assertNoProfile(array $user): void
    {
        $this->assertArrayHasKey('resource', $user);
        $this->assertNull($user['resource']);
    }

    public function test_a_user_inside_the_scope_has_a_profile(): void
    {
        $user = $this->user('ada@example.com');
        $this->actingAs($user);

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response->assertJsonPath('user.resource', 'users');
        $this->getJson(self::API.'/resources/users/'.$user->getKey())->assertOk();
    }

    public function test_a_user_outside_the_scope_has_no_profile(): void
    {
        $user = $this->user('eve@example.org');
        $this->actingAs($user);

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $this->assertNoProfile($response->json('user'));
        // The page the link would open
        $this->getJson(self::API.'/resources/users/'.$user->getKey())->assertNotFound();
    }

    public function test_a_user_who_cannot_view_their_item_has_no_profile(): void
    {
        $this->actingAs($this->user('ada@example.com'));
        UserPolicy::$denied = ['view'];

        $this->assertNoProfile($this->getJson(self::API.'/definition')->assertOk()->json('user'));
    }

    public function test_a_user_who_cannot_update_their_item_has_no_profile(): void
    {
        $this->actingAs($this->user('ada@example.com'));
        UserPolicy::$denied = ['update'];

        $this->assertNoProfile($this->getJson(self::API.'/definition')->assertOk()->json('user'));
    }

    public function test_without_a_resource_of_users_the_user_has_no_resource(): void
    {
        $this->actingAs(User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']));

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertArrayNotHasKey('resource', $response->json('user'));
    }
}
