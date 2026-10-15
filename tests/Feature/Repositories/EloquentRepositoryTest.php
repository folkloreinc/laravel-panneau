<?php

namespace Panneau\Tests\Feature\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Panneau\Contracts\Repositories\Restorable;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Query\SortDirection;
use Panneau\Repositories\EloquentRepository;
use Panneau\Repositories\SoftDeletingEloquentRepository;
use Panneau\Tests\TestCase;
use TestApp\Models\Document;
use TestApp\Models\Post;

class EloquentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var EloquentRepository<Post>
     */
    private EloquentRepository $posts;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->posts = EloquentRepository::for(Post::class);
    }

    private function createPost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + ['title' => 'A post', 'team_id' => 1]);
    }

    public function test_it_refuses_a_class_that_is_not_a_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EloquentRepository(\stdClass::class);
    }

    public function test_for_picks_the_soft_deleting_repository(): void
    {
        $this->assertNotInstanceOf(Restorable::class, $this->posts);
        $this->assertInstanceOf(SoftDeletingEloquentRepository::class, EloquentRepository::for(Document::class));
    }

    public function test_query_paginates(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createPost(['title' => 'Post '.$index]);
        }

        $page = $this->posts->query(new ResourceQuery(page: 2, perPage: 2));

        $this->assertSame(5, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(['Post 3', 'Post 4'], collect($page->items())->pluck('title')->all());
    }

    public function test_query_applies_the_scope(): void
    {
        $this->createPost(['title' => 'Mine', 'team_id' => 1]);
        $this->createPost(['title' => 'Theirs', 'team_id' => 2]);

        $page = $this->posts->query(new ResourceQuery(scope: ['team_id' => 1]));

        $this->assertSame(['Mine'], collect($page->items())->pluck('title')->all());
    }

    public function test_query_applies_ids_and_exclusions(): void
    {
        $first = $this->createPost();
        $second = $this->createPost();
        $third = $this->createPost();

        $page = $this->posts->query(
            (new ResourceQuery)->withIds([(string) $first->id, (string) $second->id])->withExcept([(string) $second->id]),
        );
        $this->assertSame([$first->id], collect($page->items())->pluck('id')->all());

        $page = $this->posts->query((new ResourceQuery)->withExcept([(string) $first->id]));
        $this->assertSame([$second->id, $third->id], collect($page->items())->pluck('id')->all());
    }

    public function test_query_applies_filters(): void
    {
        $this->createPost(['title' => 'Draft', 'status' => 'draft']);
        $this->createPost(['title' => 'Published', 'status' => 'published']);
        $this->createPost(['title' => 'Archived', 'status' => 'archived']);

        $page = $this->posts->query(new ResourceQuery(filters: ['status' => 'published']));
        $this->assertSame(['Published'], collect($page->items())->pluck('title')->all());

        $page = $this->posts->query(new ResourceQuery(filters: ['status' => ['draft', 'archived']]));
        $this->assertSame(['Draft', 'Archived'], collect($page->items())->pluck('title')->all());
    }

    public function test_query_applies_the_search_to_the_search_fields(): void
    {
        $this->createPost(['title' => 'Summer', 'body' => 'Long days']);
        $this->createPost(['title' => 'Winter', 'body' => 'Summer is far']);
        $this->createPost(['title' => 'Autumn', 'body' => 'Leaves', 'secret' => 'summer']);

        $page = $this->posts->query(new ResourceQuery(search: 'summer', searchFields: ['title', 'body']));

        $this->assertSame(['Summer', 'Winter'], collect($page->items())->pluck('title')->all());
    }

    public function test_search_wildcards_are_escaped(): void
    {
        $this->createPost(['title' => '100% sure']);
        $this->createPost(['title' => '100 ways']);

        $page = $this->posts->query(new ResourceQuery(search: '100%', searchFields: ['title']));

        $this->assertSame(['100% sure'], collect($page->items())->pluck('title')->all());
    }

    public function test_query_applies_the_sorts_then_the_key(): void
    {
        $this->createPost(['title' => 'B', 'status' => 'draft']);
        $this->createPost(['title' => 'A', 'status' => 'published']);
        $this->createPost(['title' => 'C', 'status' => 'draft']);

        $page = $this->posts->query(new ResourceQuery(sorts: [new Sort('title', SortDirection::Descending)]));
        $this->assertSame(['C', 'B', 'A'], collect($page->items())->pluck('title')->all());

        $page = $this->posts->query(new ResourceQuery(sorts: [new Sort('status')]));
        $this->assertSame(['B', 'C', 'A'], collect($page->items())->pluck('title')->all());
    }

    public function test_find_stays_in_the_scope(): void
    {
        $post = $this->createPost(['team_id' => 2]);

        $this->assertTrue($post->is($this->posts->find((string) $post->id, [])));
        $this->assertTrue($post->is($this->posts->find((string) $post->id, ['team_id' => 2])));
        $this->assertNull($this->posts->find((string) $post->id, ['team_id' => 1]));
        $this->assertNull($this->posts->find('999', []));
    }

    public function test_create_fills_the_values_and_the_scope(): void
    {
        $post = $this->posts->create(['title' => 'New', 'featured' => true], ['team_id' => 4]);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertTrue($post->exists);
        $this->assertSame('New', $post->title);
        $this->assertTrue($post->featured);
        $this->assertSame(4, $post->team_id);
        $this->assertSame('draft', $post->status);
    }

    public function test_update_changes_only_the_given_values(): void
    {
        $post = $this->createPost(['title' => 'Old', 'body' => 'Body']);

        $updated = $this->posts->update($post, ['title' => 'New']);

        $this->assertSame('New', $updated->title);
        $this->assertSame('Body', $updated->body);
        $this->assertSame('New', Post::query()->find($post->id)?->title);
    }

    public function test_update_refuses_another_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->posts->update(new \stdClass, []);
    }

    public function test_delete(): void
    {
        $post = $this->createPost();

        $this->posts->delete($post);

        $this->assertNull(Post::query()->find($post->id));
    }

    public function test_id_is_the_route_key(): void
    {
        $post = $this->createPost();
        $documents = EloquentRepository::for(Document::class);
        $document = $documents->create(['name' => 'Report'], []);

        $this->assertSame((string) $post->id, $this->posts->id($post));
        $this->assertStringStartsWith('doc_', $documents->id($document));
        $this->assertTrue($document->is($documents->find($documents->id($document), [])));
    }

    public function test_version_is_stable_until_an_update(): void
    {
        $created = $this->posts->create(['title' => 'Title', 'featured' => true], ['team_id' => 1]);
        $version = $this->posts->version($created);
        $found = $this->posts->find($this->posts->id($created), []);

        $this->assertNotNull($version);
        $this->assertNotNull($found);
        $this->assertSame($version, $this->posts->version($found));

        // Same second, so updated_at alone would not change
        $updated = $this->posts->update($found, ['title' => 'Other title']);

        $this->assertNotSame($version, $this->posts->version($updated));
    }

    public function test_duplicate_copies_into_the_scope(): void
    {
        $post = $this->createPost(['title' => 'Original', 'team_id' => 1]);

        $copy = $this->posts->duplicate($post, ['team_id' => 1]);

        $this->assertNotSame($post->id, $copy->id);
        $this->assertSame('Original', $copy->title);
        $this->assertSame(2, Post::query()->count());
    }

    public function test_chunk_walks_every_matching_item(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createPost(['title' => 'Post '.$index, 'team_id' => $index === 3 ? 2 : 1]);
        }

        $chunks = [];
        $this->posts->chunk(
            new ResourceQuery(page: 3, perPage: 1, sorts: [new Sort('title', SortDirection::Descending)], scope: ['team_id' => 1]),
            2,
            function ($items) use (&$chunks): void {
                $chunks[] = $items->pluck('title')->all();
            },
        );

        $this->assertSame([['Post 1', 'Post 2'], ['Post 4', 'Post 5']], $chunks);
    }

    public function test_chunk_stops_when_the_callback_returns_false(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createPost();
        }

        $count = 0;
        $this->posts->chunk(new ResourceQuery, 2, function () use (&$count): bool {
            $count++;

            return false;
        });

        $this->assertSame(1, $count);
    }

    public function test_soft_deleted_items_can_be_found_and_restored(): void
    {
        /** @var SoftDeletingEloquentRepository<Document> $documents */
        $documents = EloquentRepository::for(Document::class);
        $document = $documents->create(['name' => 'Report'], []);
        $id = $documents->id($document);

        $documents->delete($document);

        $this->assertNull($documents->find($id, []));
        $deleted = $documents->findDeleted($id, []);
        $this->assertNotNull($deleted);

        $restored = $documents->restore($deleted);

        $this->assertFalse($restored->trashed());
        $this->assertNotNull($documents->find($id, []));
        $this->assertNull($documents->findDeleted($id, []));
    }
}
