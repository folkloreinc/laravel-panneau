<?php

namespace Panneau\Tests\Feature\Roles;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Roles\RoleManager;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Tag;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Roles\AuthorPolicy;
use TestApp\Roles\ContestPolicy;
use TestApp\Roles\ContestsResource;
use TestApp\Roles\PrizePolicy;
use TestApp\Roles\PrizesResource;
use TestApp\Roles\UserPolicy;
use TestApp\Roles\UsersResource;

/**
 * The roles module (decision 0014), on the roles of the contests platform: admins, managers,
 * and clients who reach the contests of their team only, and edit prizes when granted.
 */
#[WithMigration]
class RolesModuleTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesProtocol;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('panneau.locales', ['en']);
        $app['config']->set('panneau.roles', [
            'enabled' => true,
            'roles' => [
                'admin' => ['label' => 'Admin', 'permissions' => ['*']],
                'manager' => ['label' => 'Manager', 'permissions' => ['contests.*', 'prizes.*', 'authors.*', 'users.viewAny', 'users.view', 'users.update', 'stats.view']],
                'client' => [
                    'label' => 'Client',
                    'permissions' => ['contests.viewAny', 'contests.view', 'contests.publish-posts', 'prizes.viewAny', 'prizes.view', 'authors.viewAny', 'stats.view'],
                    'owned' => true,
                ],
            ],
            'grantable' => ['prizes.update', 'contests.budget.view'],
            'permissions' => ['stats.view'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Post::class, ContestPolicy::class);
        Gate::policy(Tag::class, PrizePolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Panneau::resources([ContestsResource::class, PrizesResource::class, AuthorsResource::class, UsersResource::class]);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    /**
     * A user with roles and grants, of a team.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $grants
     */
    private function user(int $id, array $roles, array $grants = [], int $team = 1): GenericUser
    {
        $user = new GenericUser(['id' => $id, 'name' => 'User '.$id, 'team_id' => $team]);
        $manager = $this->app->make(RoleManager::class);
        $manager->syncRoles($user, $roles);
        $manager->syncGrants($user, $grants);

        return $user;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definition(): array
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();
        $this->assertValidDefinition($response->json());

        return collect($response->json('resources'))->keyBy('id')->all();
    }

    private function contest(int $team, string $title = 'A contest'): Post
    {
        return Post::query()->create(['title' => $title, 'team_id' => $team, 'body' => 'Budget']);
    }

    public function test_the_gate_answers_the_permissions_of_panneau_and_nothing_else(): void
    {
        Gate::define('viewHorizon', fn (): bool => false);
        $admin = $this->user(1, ['admin']);
        $client = $this->user(2, ['client']);

        $this->assertTrue(Gate::forUser($admin)->allows('contests.update'));
        $this->assertTrue(Gate::forUser($admin)->allows('stats.view'));
        // Not a permission of Panneau: the project's own gate answers
        $this->assertFalse(Gate::forUser($admin)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($client)->allows('stats.view'));
        $this->assertFalse(Gate::forUser($client)->allows('contests.update'));
        $this->assertFalse(Gate::forUser($this->user(3, []))->allows('stats.view'));
    }

    public function test_users_without_a_role_cant_open_the_admin(): void
    {
        $this->actingAs($this->user(3, []));
        $this->getJson(self::API.'/definition')->assertForbidden();

        $this->actingAs($this->user(2, ['client']));
        $this->getJson(self::API.'/definition')->assertOk();
    }

    public function test_the_definition_follows_the_roles_of_the_user(): void
    {
        $this->actingAs($this->user(1, ['manager']));
        $resources = $this->definition();
        $this->assertSame(['viewAny' => true, 'create' => true], $resources['contests']['abilities']);
        $this->assertNotContains('delete', array_column($resources['contests']['actions'], 'id'));

        $this->actingAs($this->user(2, ['client']));
        $resources = $this->definition();
        $this->assertSame(['viewAny' => true, 'create' => false], $resources['contests']['abilities']);
        // Clients can't delete contests: no "Delete" in lists
        $this->assertSame(['run' => false], collect($resources['contests']['actions'])->firstWhere('id', 'delete')['abilities']);
        $this->assertSame(['run' => true], collect($resources['contests']['actions'])->firstWhere('id', 'publish-posts')['abilities']);
        // A resource that doesn't say what clients own is closed to them
        $this->assertFalse($resources['authors']['abilities']['viewAny']);
        $this->assertSame(['view' => false], collect($resources['contests']['fields'])->firstWhere('name', 'body')['abilities']);
        // The roles of users are only for those who can assign them
        $this->assertFalse($resources['users']['abilities']['viewAny']);
    }

    public function test_clients_reach_only_the_contests_of_their_team_on_every_route(): void
    {
        $own = $this->contest(1, 'Own');
        $other = $this->contest(2, 'Other');
        $this->actingAs($this->user(2, ['client']));

        $listed = $this->getJson(self::API.'/resources/contests')->assertOk()->json('data');
        $this->assertSame(['Own'], array_column($listed, 'title'));
        $this->assertSame(['view' => true, 'update' => false, 'delete' => false, 'duplicate' => false, 'publish-posts' => true], $listed[0]['abilities']);
        $this->assertArrayNotHasKey('body', $listed[0]);

        $path = self::API.'/resources/contests/'.$other->getKey();
        $this->getJson($path)->assertNotFound();
        $this->patchJson($path, ['title' => 'Mine'])->assertNotFound();
        $this->deleteJson($path)->assertNotFound();
        $this->postJson($path.'/duplicate')->assertNotFound();
        $this->postJson(self::API.'/resources/contests/actions/publish-posts', ['selection' => ['ids' => [(string) $other->getKey()]]])->assertNotFound();
        $this->assertSame('Other', $other->fresh()?->title);

        $this->getJson(self::API.'/resources/contests/'.$own->getKey())->assertOk();
        $this->patchJson(self::API.'/resources/contests/'.$own->getKey(), ['title' => 'Changed'])->assertForbidden();
        $this->postJson(self::API.'/resources/contests', ['title' => 'New'])->assertForbidden();
        $this->postJson(self::API.'/resources/contests/actions/publish-posts', ['selection' => ['ids' => [(string) $own->getKey()]]])->assertOk();
        $this->assertSame('published', $own->fresh()?->status);
    }

    public function test_the_policy_checks_ownership_again_on_the_item(): void
    {
        $other = $this->contest(2);
        $client = $this->user(2, ['client']);

        $this->assertFalse(Gate::forUser($client)->allows('view', $other));
        $this->assertTrue(Gate::forUser($client)->allows('view', $this->contest(1)));
        // With another role too, a user isn't limited to their items
        $this->assertTrue(Gate::forUser($this->user(4, ['client', 'manager']))->allows('view', $other));
    }

    public function test_a_grant_adds_a_permission_to_one_user(): void
    {
        $prize = Tag::query()->create(['name' => 'A trip', 'slug' => 'trip']);
        $path = self::API.'/resources/prizes/'.$prize->getRouteKey();

        $this->actingAs($this->user(2, ['client']));
        $this->getJson($path)->assertOk()->assertJsonPath('data.abilities.update', false);
        $this->patchJson($path, ['name' => 'A cruise'])->assertForbidden();

        $this->actingAs($this->user(5, ['client'], ['prizes.update', 'contests.budget.view']));
        $this->getJson($path)->assertOk()->assertJsonPath('data.abilities.update', true);
        $this->patchJson($path, ['name' => 'A cruise'])->assertOk();
        $this->assertSame('A cruise', $prize->fresh()?->name);
        $this->assertArrayNotHasKey('abilities', collect($this->definition()['contests']['fields'])->firstWhere('name', 'body'));
    }

    public function test_admins_assign_roles_and_grants_with_the_fields_of_users(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret'])->save();
        $path = self::API.'/resources/users/'.$user->getKey();

        $this->actingAs($this->user(1, ['admin']));
        $fields = collect($this->definition()['users']['fields'])->keyBy('name');
        $this->assertSame(['type' => 'select', 'multiple' => true], array_intersect_key($fields['roles'], ['type' => true, 'multiple' => true]));
        $this->assertSame(['admin', 'manager', 'client'], array_column($fields['roles']['options'], 'value'));
        $this->patchJson($path, ['roles' => ['client'], 'grants' => ['prizes.update']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['client'])
            ->assertJsonPath('data.grants', ['prizes.update']);
        $manager = $this->app->make(RoleManager::class);
        $this->assertSame(['client'], $manager->rolesOf($user->fresh()));

        // Managers see users, but can't change their roles
        $this->actingAs($this->user(6, ['manager']));
        $this->assertSame(['update' => false], collect($this->definition()['users']['fields'])->firstWhere('name', 'roles')['abilities']);
        $this->patchJson($path, ['name' => 'Grace Hopper', 'roles' => ['admin']])->assertOk()->assertJsonPath('data.roles', ['client']);
        $this->assertSame(['client'], $manager->rolesOf($user->fresh()));
        $this->assertSame('Grace Hopper', $user->fresh()?->name);
    }

    public function test_the_role_command_sets_the_roles_of_a_user(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret'])->save();

        $this->artisan('panneau:role', ['user' => 'ada@example.com', 'roles' => ['admin']])->assertSuccessful();
        $this->artisan('panneau:role', ['user' => (string) $user->getKey(), '--grant' => ['prizes.update']])->assertSuccessful();
        $this->artisan('panneau:role', ['user' => 'ada@example.com', 'roles' => ['owner']])->assertFailed();
        $this->artisan('panneau:role', ['user' => 'nobody@example.com', 'roles' => ['admin']])->assertFailed();

        $manager = $this->app->make(RoleManager::class);
        $this->assertSame(['admin'], $manager->rolesOf($user));
        $this->assertSame(['prizes.update'], $manager->grantsOf($user));
    }
}
