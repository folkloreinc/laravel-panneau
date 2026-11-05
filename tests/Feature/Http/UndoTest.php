<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Gate;
use Panneau\Actions\ActionResult;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Document;
use TestApp\Models\Post;
use TestApp\Panneau\Actions\FeaturePosts;
use TestApp\Panneau\Resources\DocumentsTrashResource;

/**
 * What can be undone runs without a confirmation, and its result says how to undo it
 * (panneau-js decision 0063, #179).
 */
class UndoTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([DocumentsTrashResource::class]);
        // Documents have no policy: every item allows everything
        Panneau::allowWithoutPolicies();
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function actions(string $resource): array
    {
        $resources = array_column($this->getJson(self::API.'/definition')->json('resources'), null, 'id');

        return array_column($resources[$resource]['actions'] ?? [], null, 'id');
    }

    public function test_the_delete_of_a_resource_with_a_trash_is_undoable(): void
    {
        $actions = $this->actions('documents');

        $this->assertSame(['id' => 'delete', 'kind' => 'server', 'scope' => ['item', 'selection'], 'undoable' => true], $actions['delete']);
        // Restore is run as the undo, never offered
        $this->assertArrayNotHasKey('restore', $actions);
    }

    public function test_the_delete_of_a_resource_without_a_trash_keeps_its_confirmation(): void
    {
        $this->assertArrayNotHasKey('delete', $this->actions('posts'));
    }

    public function test_delete_says_how_to_undo_it_and_restore_puts_the_items_back(): void
    {
        $first = Document::query()->create(['name' => 'First']);
        $second = Document::query()->create(['name' => 'Second']);

        $result = $this->postJson(self::API.'/resources/documents/actions/delete', [
            'selection' => ['ids' => [$first->uid, $second->uid, 'doc_missing']],
        ])->assertOk()->json();

        // The items actually deleted, not the skipped one
        $this->assertSame(['action' => 'restore', 'items' => [$first->uid, $second->uid]], $result['undo']);
        $this->assertSame(0, Document::query()->count());

        $this->postJson(self::API.'/resources/documents/actions/restore', [
            'selection' => ['ids' => $result['undo']['items']],
        ])->assertOk()->assertJsonPath('summary.succeeded', 2)->assertJsonMissingPath('undo');

        $this->assertSame(['First', 'Second'], Document::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_restore_only_finds_deleted_items_by_their_ids(): void
    {
        $document = Document::query()->create(['name' => 'Kept']);

        // An item that isn't in the trash is not found
        $this->postJson(self::API.'/resources/documents/actions/restore', [
            'selection' => ['ids' => [$document->uid]],
        ])->assertNotFound();
        $this->postJson(self::API.'/resources/documents/actions/restore', [
            'selection' => ['query' => []],
        ])->assertUnprocessable();
        // A resource without a trash has no restore
        $this->postJson(self::API.'/resources/posts/actions/restore', [
            'selection' => ['ids' => ['1']],
        ])->assertNotFound();
    }

    public function test_restore_asks_the_restore_method_of_the_policy(): void
    {
        $document = Document::query()->create(['name' => 'Deleted']);
        $document->delete();
        Gate::policy(Document::class, RestoreDeniedPolicy::class);

        $this->postJson(self::API.'/resources/documents/actions/restore', [
            'selection' => ['ids' => [$document->uid]],
        ])->assertForbidden();
        $this->assertSame(0, Document::query()->count());
    }

    public function test_an_action_declares_its_inverse(): void
    {
        $actions = $this->actions('posts');

        $this->assertTrue($actions['feature-posts']['undoable']);
        $this->assertArrayNotHasKey('undoable', $actions['publish-posts']);
        // The inverse is offered nowhere, but runs by the same abilities
        $this->assertSame([], $actions['unfeature-posts']['placement']);
    }

    public function test_the_undo_holds_the_items_that_succeeded_and_puts_them_back(): void
    {
        $plain = $this->createPost(['title' => 'Plain']);
        $featured = $this->createPost(['title' => 'Featured', 'featured' => true]);

        $result = $this->postJson(self::API.'/resources/posts/actions/feature-posts', [
            'selection' => ['ids' => [(string) $plain->id, (string) $featured->id]],
        ])->assertOk()->json();

        $this->assertSame(['action' => 'unfeature-posts', 'items' => [(string) $plain->id]], $result['undo']);
        $this->assertTrue($plain->refresh()->featured);

        $this->postJson(self::API.'/resources/posts/actions/'.$result['undo']['action'], [
            'selection' => ['ids' => $result['undo']['items']],
        ])->assertOk();

        $this->assertFalse($plain->refresh()->featured);
        // What the action skipped is left as it was
        $this->assertTrue($featured->refresh()->featured);
    }

    public function test_no_undo_when_nothing_succeeded(): void
    {
        $featured = $this->createPost(['featured' => true]);
        $other = $this->createPost(['featured' => true]);

        $this->postJson(self::API.'/resources/posts/actions/feature-posts', [
            'selection' => ['ids' => [(string) $featured->id, (string) $other->id]],
        ])->assertOk()->assertJsonMissingPath('undo');
    }

    public function test_no_undo_beyond_the_limit_of_the_protocol(): void
    {
        $ids = [];
        for ($index = 0; $index < 101; $index++) {
            $ids[] = (string) $this->createPost(['title' => 'Post '.$index])->id;
        }

        $this->postJson(self::API.'/resources/posts/actions/feature-posts', [
            'selection' => ['ids' => $ids],
        ])->assertOk()->assertJsonPath('summary.succeeded', 101)->assertJsonMissingPath('undo');

        $this->postJson(self::API.'/resources/posts/actions/feature-posts', [
            'selection' => ['ids' => [(string) Post::query()->create(['title' => 'One more', 'team_id' => 1])->id]],
        ])->assertOk()->assertJsonPath('undo.action', 'unfeature-posts');
    }

    public function test_the_parameters_of_the_inverse(): void
    {
        $action = (new FeaturePosts)->undoneBy('unfeature-posts', ['reason' => 'undo']);
        $this->assertSame('unfeature-posts', $action->inverse());
        $this->assertTrue($action->toDefinition()['undoable']);

        $result = (new ActionResult(fn (object $item): string => (string) $item->id))
            ->undo($action->inverse(), $action->inverseParams());

        $this->assertSame(
            ['action' => 'unfeature-posts', 'items' => ['1', '2'], 'params' => ['reason' => 'undo']],
            $result->toArray(['1', '2'])['undo'],
        );
    }
}

/**
 * Lets everything but restoring.
 */
class RestoreDeniedPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function delete(): bool
    {
        return true;
    }

    public function restore(): bool
    {
        return false;
    }
}
