<?php

namespace Panneau\Tests\Feature\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Panneau\Contracts\Repositories\Restorable;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Query\SortDirection;
use Panneau\Repositories\EloquentRepository;
use Panneau\Repositories\SoftDeletingEloquentRepository;
use Panneau\Tests\TestCase;
use TestApp\Models\Author;
use TestApp\Models\Document;
use TestApp\Models\Post;
use TestApp\Models\Tag;

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

    public function test_cursor_query_follows_the_cursors(): void
    {
        foreach (range(1, 5) as $day) {
            $this->createPost(['title' => 'Day '.$day, 'published_at' => '2026-10-0'.$day.' 10:00:00']);
        }
        $this->createPost(['title' => 'Elsewhere', 'team_id' => 2, 'published_at' => '2026-10-09 10:00:00']);
        $query = new ResourceQuery(perPage: 2, sorts: [new Sort('published_at', SortDirection::Descending)], scope: ['team_id' => 1]);
        $titles = fn ($page): array => array_map(fn (Post $post): string => $post->title, $page->items);

        $first = $this->posts->cursorQuery($query);
        $this->assertSame(['Day 5', 'Day 4'], $titles($first));
        $this->assertNull($first->previousCursor);
        $this->assertNull($first->total);

        $second = $this->posts->cursorQuery($query->withCursor($first->nextCursor));
        $this->assertSame(['Day 3', 'Day 2'], $titles($second));
        $this->assertNull($second->previousCursor);

        $third = $this->posts->cursorQuery($query->withCursor($second->nextCursor));
        $this->assertSame(['Day 1'], $titles($third));
        $this->assertNull($third->nextCursor);
        $this->assertIsString($third->previousCursor);

        $back = $this->posts->cursorQuery($query->withCursor($third->previousCursor));
        $this->assertSame(['Day 3', 'Day 2'], $titles($back));
        $this->assertNull($back->previousCursor);
        $this->assertSame(['perPage' => 2, 'nextCursor' => $back->nextCursor, 'previousCursor' => null], $back->meta());
    }

    public function test_cursor_query_refuses_an_unreadable_cursor(): void
    {
        $this->createPost();
        $query = new ResourceQuery(perPage: 2, sorts: [new Sort('title')]);
        $cursor = fn (array $parameters, bool $next = true): string => (new Cursor($parameters, $next))->encode();

        foreach ([
            'not a cursor',
            base64_encode('{"title":"B"}'),
            // Without the number of its page
            $cursor(['title' => 'B', 'posts.id' => 2]),
            $cursor(['title' => 'B', 'posts.id' => 2, '_page' => 1]),
            $cursor(['title' => ['B'], 'posts.id' => 2, '_page' => 2]),
            base64_encode('{"title":"B","posts.id":2,"_page":2,"_pointsToNextItems":"yes"}'),
            // From another sort
            $cursor(['published_at' => '2026-10-30', '_page' => 2]),
            str_repeat('a', 5000),
        ] as $invalid) {
            try {
                $this->posts->cursorQuery($query->withCursor($invalid));
                $this->fail('The cursor '.$invalid.' was read.');
            } catch (ValidationException $exception) {
                $this->assertSame(['cursor'], array_keys($exception->errors()));
            }
        }
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

    public function test_a_search_on_no_field_finds_nothing(): void
    {
        $this->createPost(['title' => 'Summer']);

        $this->assertSame(0, $this->posts->query(new ResourceQuery(search: 'summer'))->total());
        $this->assertSame(1, $this->posts->query(new ResourceQuery)->total());
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

    public function test_lists_in_the_scope_are_constraints(): void
    {
        $this->createPost(['title' => 'Draft', 'status' => 'draft']);
        $this->createPost(['title' => 'Published', 'status' => 'published']);
        $archived = $this->createPost(['title' => 'Archived', 'status' => 'archived']);
        $scope = ['team_id' => 1, 'status' => ['draft', 'published']];

        $this->assertSame(['Draft', 'Published'], collect($this->posts->query(new ResourceQuery(scope: $scope))->items())->pluck('title')->all());
        $this->assertNull($this->posts->find((string) $archived->id, $scope));

        // A list is not filled in
        $post = $this->posts->create(['title' => 'New'], $scope);
        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame(['team_id' => 1, 'status' => 'draft'], $post->only(['team_id', 'status']));
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

    public function test_belongs_to_relations_receive_public_ids(): void
    {
        $author = Author::query()->create(['name' => 'Ada']);

        $post = $this->posts->create(['title' => 'Post', 'author' => (string) $author->id], []);
        $this->assertSame($author->id, $post->author_id);

        $post = $this->posts->update($post, ['author' => null]);
        $this->assertNull($post->author_id);
    }

    public function test_belongs_to_many_relations_are_synced_by_public_id(): void
    {
        $news = Tag::query()->create(['slug' => 'news', 'name' => 'News']);
        $tech = Tag::query()->create(['slug' => 'tech', 'name' => 'Tech']);

        $post = $this->posts->create(['title' => 'Post', 'tags' => ['news', 'tech']], []);
        $this->assertEqualsCanonicalizing([$news->id, $tech->id], $post->tags()->pluck('tags.id')->all());

        $post = $this->posts->update($post, ['tags' => ['tech']]);
        $this->assertSame([$tech->id], $post->tags()->pluck('tags.id')->all());

        $post = $this->posts->update($post, ['tags' => null]);
        $this->assertSame([], $post->tags()->pluck('tags.id')->all());
    }

    public function test_methods_that_are_not_relations_are_never_called(): void
    {
        $post = $this->createPost(['status' => 'draft']);

        try {
            $this->posts->update($post, ['archive' => 'yes']);
        } catch (QueryException) {
            // There is no "archive" column
        }

        $this->assertSame('draft', Post::query()->find($post->id)?->status);
    }
}
