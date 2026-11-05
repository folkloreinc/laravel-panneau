<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Support\Facade as Panneau;
use TestApp\Models\Document;
use TestApp\Panneau\Resources\DocumentsInUseResource;

/**
 * An item that something else uses can't be deleted: `delete` skips it with the reason `in_use`
 * and deletes the others (#189).
 */
class ItemInUseTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([DocumentsInUseResource::class]);
        // Documents have no policy: every item allows everything
        Panneau::allowWithoutPolicies();
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_an_item_in_use_is_skipped_alone_without_an_undo(): void
    {
        $used = Document::query()->create(['name' => 'In use']);

        $this->postJson(self::API.'/resources/documents/actions/delete', [
            'selection' => ['ids' => [$used->uid]],
        ])
            ->assertOk()
            ->assertExactJson([
                'summary' => ['succeeded' => 0, 'failed' => 0, 'skipped' => 1],
                'results' => [['id' => $used->uid, 'status' => 'skipped', 'reason' => 'in_use']],
                'refresh' => 'list',
            ]);

        $this->assertNotSoftDeleted($used);
    }

    public function test_the_others_are_deleted_and_the_undo_covers_only_those(): void
    {
        $first = Document::query()->create(['name' => 'First']);
        $used = Document::query()->create(['name' => 'In use']);
        $last = Document::query()->create(['name' => 'Last']);

        $result = $this->postJson(self::API.'/resources/documents/actions/delete', [
            'selection' => ['ids' => [$first->uid, $used->uid, $last->uid]],
        ])->assertOk()->json();

        $this->assertSame(['succeeded' => 2, 'failed' => 0, 'skipped' => 1], $result['summary']);
        $this->assertSame([['id' => $used->uid, 'status' => 'skipped', 'reason' => 'in_use']], $result['results']);
        $this->assertSame(['action' => 'restore', 'items' => [$first->uid, $last->uid]], $result['undo']);
        $this->assertSame(['In use'], Document::query()->pluck('name')->all());
    }

    public function test_a_query_selection_skips_it_too(): void
    {
        Document::query()->create(['name' => 'First']);
        Document::query()->create(['name' => 'In use']);

        $this->postJson(self::API.'/resources/documents/actions/delete', ['selection' => ['query' => []]])
            ->assertOk()
            ->assertJsonPath('summary', ['succeeded' => 1, 'failed' => 0, 'skipped' => 1])
            ->assertJsonPath('results.0.reason', 'in_use');
        $this->assertSame(['In use'], Document::query()->pluck('name')->all());
    }

    public function test_a_delete_of_the_item_answers_a_conflict(): void
    {
        $used = Document::query()->create(['name' => 'In use']);

        $this->deleteJson(self::API.'/resources/documents/'.$used->uid)
            ->assertStatus(409)
            ->assertJsonPath('title', 'This item is in use elsewhere: remove it from where it is used, then try again.');
        $this->assertNotSoftDeleted($used);
    }
}
