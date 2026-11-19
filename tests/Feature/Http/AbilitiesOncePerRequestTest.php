<?php

namespace Panneau\Tests\Feature\Http;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Text;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Policies\PostPolicy;

/**
 * The abilities of a list are checked once per resource, and once per item (#244): what only
 * depends on the resource (its actions, `runAction` without an item, `create`, the fields the
 * user can't see) doesn't grow with the number of items.
 */
class AbilitiesOncePerRequestTest extends HttpTestCase
{
    /**
     * Distinct abilities checked through the Gate on each post: `view`, `update`, `delete` and
     * `publishPosts`. The other actions fall back to `update`, and `duplicate` to `create`.
     */
    private const CHECKS_PER_ITEM = 4;

    private int $policies = 0;

    private int $actions = 0;

    private int $fieldChecks = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([CountedPostsResource::class]);
        Gate::define('seePostNotes', function (): bool {
            $this->fieldChecks++;

            return true;
        });
        // The Gate resolves the policy for each check; Panneau resolves it to find its methods
        $this->app->resolving(PostPolicy::class, function (): void {
            $this->policies++;
        });
        $this->app->resolving(ActionDeclaration::class, function (): void {
            $this->actions++;
        });
    }

    public function test_a_list_checks_the_resource_once_and_each_item_once(): void
    {
        $ten = $this->countList(10);
        $twenty = $this->countList(20);

        $this->assertSame($ten['actions'], $twenty['actions']);
        $this->assertSame($ten['fieldChecks'], $twenty['fieldChecks']);
        $this->assertSame($ten['policies'] + 10 * self::CHECKS_PER_ITEM, $twenty['policies']);
    }

    public function test_the_search_checks_the_resource_once_and_each_item_once(): void
    {
        $ten = $this->countSearch(10);
        $twenty = $this->countSearch(20);

        $this->assertSame($ten['actions'], $twenty['actions']);
        $this->assertSame($ten['fieldChecks'], $twenty['fieldChecks']);
        $this->assertSame($ten['policies'] + 10 * self::CHECKS_PER_ITEM, $twenty['policies']);
    }

    public function test_the_abilities_are_checked_again_on_the_next_request(): void
    {
        $this->createPosts(2);
        $this->getJson(self::API.'/resources/counted-posts')->assertOk()
            ->assertJsonPath('data.0.abilities.publish-posts', true);

        PostPolicy::$denied = ['publishPosts', 'create'];

        $this->getJson(self::API.'/resources/counted-posts')->assertOk()
            ->assertJsonPath('data.0.abilities.publish-posts', false)
            ->assertJsonPath('data.0.abilities.duplicate', false);
    }

    public function test_the_answers_are_kept_by_user_within_a_request(): void
    {
        Gate::define('seePostNotes', fn ($user): bool => $user->getAuthIdentifier() === 1);
        $authorizer = $this->app->make(Authorizer::class);
        $resource = Panneau::resource('counted-posts');
        $this->assertNotNull($resource);
        $ada = new GenericUser(['id' => 1, 'name' => 'Ada']);
        $grace = new GenericUser(['id' => 2, 'name' => 'Grace']);

        $this->assertSame([], $authorizer->hiddenFields($ada, $resource));
        $this->assertSame(['notes'], $authorizer->hiddenFields($grace, $resource));
        $this->assertSame(['notes'], $authorizer->hiddenFields(null, $resource));
        $this->assertSame([], $authorizer->hiddenFields($ada, $resource));
    }

    /**
     * @return array{policies: int, actions: int, fieldChecks: int}
     */
    private function countList(int $items): array
    {
        $this->createPosts($items);

        return $this->counted(fn () => $this->getJson(self::API.'/resources/counted-posts?perPage='.$items)
            ->assertOk()->assertJsonCount($items, 'data'));
    }

    /**
     * @return array{policies: int, actions: int, fieldChecks: int}
     */
    private function countSearch(int $items): array
    {
        $this->createPosts($items);

        return $this->counted(fn () => $this->getJson(self::API.'/search?resources=counted-posts&q=Counted&limit='.$items)
            ->assertOk()->assertJsonCount($items, 'data.0.items'));
    }

    private function createPosts(int $count): void
    {
        Post::query()->delete();
        foreach (range(1, $count) as $index) {
            $this->createPost(['title' => 'Counted '.$index]);
        }
    }

    /**
     * @return array{policies: int, actions: int, fieldChecks: int}
     */
    private function counted(Closure $request): array
    {
        $this->policies = 0;
        $this->actions = 0;
        $this->fieldChecks = 0;
        $request();

        return ['policies' => $this->policies, 'actions' => $this->actions, 'fieldChecks' => $this->fieldChecks];
    }
}

class CountedPostsResource extends PostsResource
{
    public function id(): string
    {
        return 'counted-posts';
    }

    public function fields(): array
    {
        return [...parent::fields(), Text::make('notes')->canSee('seePostNotes')];
    }
}
