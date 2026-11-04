<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Panneau\Http\Problem;
use Panneau\Support\Facade;
use TestApp\Models\Author;
use TestApp\Models\Tag;
use TestApp\Policies\PostPolicy;

class ResourceListTest extends HttpTestCase
{
    public function test_a_page_of_items(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createPost(['title' => 'Post '.$index, 'published_at' => '2026-10-0'.$index.' 10:00:00']);
        }

        $response = $this->getJson(self::API.'/resources/posts?page=2&perPage=2')->assertOk();

        $response->assertJsonPath('meta', ['page' => 2, 'perPage' => 2, 'total' => 5, 'lastPage' => 3]);
        // Default sort: -published_at
        $this->assertSame(['Post 3', 'Post 2'], array_column($response->json('data'), 'title'));
    }

    public function test_lists_find_the_people_and_roles_of_credits_at_once(): void
    {
        $authors = array_map(fn (string $name): Author => Author::query()->create(['name' => $name]), ['Ada', 'Grace', 'Edsger']);
        $role = Tag::query()->create(['name' => 'Direction', 'slug' => 'direction']);
        foreach ($authors as $index => $author) {
            $this->createPost(['title' => 'Post '.$index, 'credits' => [
                ['person' => ['id' => (string) $author->id], 'roles' => [['id' => $role->slug]]],
                ['person' => ['id' => (string) $authors[($index + 1) % 3]->id], 'roles' => []],
                ['person' => ['name' => 'Jane Doe'], 'roles' => []],
            ]]);
        }

        DB::enableQueryLog();
        $response = $this->getJson(self::API.'/resources/posts')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertCount(3, $response->json('data'));
        foreach ($response->json('data') as $item) {
            $this->assertIsString($item['credits'][0]['person']['label']);
            $this->assertSame([['id' => $role->slug, 'label' => 'Direction']], $item['credits'][0]['roles']);
            $this->assertSame(['name' => 'Jane Doe'], $item['credits'][2]['person']);
        }
        $this->assertCount(1, array_filter($queries, fn (string $query): bool => str_starts_with($query, 'select * from "authors"')));
        $this->assertCount(1, array_filter($queries, fn (string $query): bool => str_starts_with($query, 'select * from "tags"')));
    }

    public function test_items_have_an_id_a_version_and_abilities(): void
    {
        $post = $this->createPost(['title' => 'Locked', 'secret' => 'hidden']);

        $item = $this->getJson(self::API.'/resources/posts')->json('data.0');

        $this->assertSame((string) $post->id, $item['id']);
        $this->assertIsString($item['version']);
        $this->assertSame(
            ['view' => true, 'update' => false, 'delete' => false, 'duplicate' => true, 'publish-posts' => true, 'archive-posts' => false],
            $item['abilities'],
        );
        $this->assertArrayNotHasKey('secret', $item);
        $this->assertArrayNotHasKey('team_id', $item);
    }

    public function test_items_have_the_abilities_of_the_built_in_actions(): void
    {
        // The app shows show, edit, delete and duplicate on an item by its abilities
        Facade::allowWithoutPolicies();
        $this->createPost();
        Author::query()->create(['name' => 'Ada']);
        Tag::query()->create(['name' => 'News', 'slug' => 'news']);

        foreach (['posts', 'authors', 'tags'] as $resource) {
            $items = $this->getJson(self::API.'/resources/'.$resource)->assertOk()->json('data');
            $this->assertNotEmpty($items, $resource);
            foreach ($items as $item) {
                foreach (['view', 'update', 'delete', 'duplicate'] as $ability) {
                    $this->assertIsBool($item['abilities'][$ability] ?? null, $resource.': '.$ability);
                }
                // Links don't run: they aren't abilities of their own
                $this->assertArrayNotHasKey('edit', $item['abilities'], $resource);
                $this->assertArrayNotHasKey('show', $item['abilities'], $resource);
            }
        }
    }

    public function test_items_say_whether_they_can_be_duplicated(): void
    {
        Author::query()->create(['name' => 'Ada']);
        Author::query()->create(['name' => 'Unique']);

        // The duplicate method of the policy, when it has one
        $abilities = array_column($this->getJson(self::API.'/resources/authors')->assertOk()->json('data'), 'abilities', 'name');
        $this->assertTrue($abilities['Ada']['duplicate']);
        $this->assertFalse($abilities['Unique']['duplicate']);
    }

    public function test_an_empty_list(): void
    {
        $this->getJson(self::API.'/resources/posts')
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['page' => 1, 'perPage' => 20, 'total' => 0, 'lastPage' => 1]]);
    }

    public function test_the_scope_applies(): void
    {
        $this->createPost(['title' => 'Mine']);
        $this->createPost(['title' => 'Theirs', 'team_id' => 2]);

        $this->assertSame(['Mine'], array_column($this->getJson(self::API.'/resources/posts')->json('data'), 'title'));
    }

    public function test_filters_sorts_search_and_fields(): void
    {
        $this->createPost(['title' => 'B summer', 'status' => 'published', 'featured' => true]);
        $this->createPost(['title' => 'A summer', 'status' => 'published']);
        $this->createPost(['title' => 'C summer', 'status' => 'draft']);
        $this->createPost(['title' => 'D winter', 'status' => 'published']);

        $response = $this->getJson(self::API.'/resources/posts?'.http_build_query([
            'filter' => ['status' => ['published'], 'secret' => 'x'],
            'sort' => 'title,secret',
            'q' => 'summer',
            'fields' => 'title,secret',
        ]))->assertOk();

        $this->assertSame(['A summer', 'B summer'], array_column($response->json('data'), 'title'));
        $this->assertSame(['id', 'version', 'abilities', 'title'], array_keys($response->json('data.0')));

        $response = $this->getJson(self::API.'/resources/posts?filter[featured]=true&sort=-title');
        $this->assertSame(['B summer'], array_column($response->json('data'), 'title'));
    }

    public function test_a_date_range_filter(): void
    {
        $this->createPost(['title' => 'December', 'published_at' => '2025-12-31 23:59:59']);
        $this->createPost(['title' => 'January 1', 'published_at' => '2026-01-01 00:00:00']);
        $this->createPost(['title' => 'January 31', 'published_at' => '2026-01-31 23:30:00']);
        $this->createPost(['title' => 'February', 'published_at' => '2026-02-01 00:00:00']);
        $this->createPost(['title' => 'Unpublished']);
        $titles = fn (string $query): array => array_column($this->getJson(self::API.'/resources/posts?sort=published_at&'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['January 1', 'January 31'], $titles('filter[published_at][from]=2026-01-01&filter[published_at][to]=2026-01-31'));
        $this->assertSame(['December', 'January 1', 'January 31'], $titles('filter[published_at][to]=2026-01-31'));
        $this->assertSame(['February'], $titles('filter[published_at][from]=2026-02-01'));
        $this->assertSame(['Unpublished', 'December', 'January 1', 'January 31', 'February'], $titles('filter[published_at][from]=someday'));
        $this->assertSame([], $titles('filter[published_at][from]=9999-12-31'));
    }

    public function test_invalid_parameters_are_ignored(): void
    {
        $this->createPost();

        $this->getJson(self::API.'/resources/posts?page=abc&perPage=-1&sort=-secret&filter=x&fields=')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.perPage', 20);
        $this->getJson(self::API.'/resources/posts?perPage=1000')->assertJsonPath('meta.perPage', 100);
    }

    public function test_an_unknown_resource_is_a_404_problem(): void
    {
        $this->getJson(self::API.'/resources/unknown')
            ->assertNotFound()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('status', 404)
            ->assertJsonPath('type', 'about:blank');
    }

    public function test_view_any_is_required(): void
    {
        PostPolicy::$denied = ['viewAny'];

        $this->getJson(self::API.'/resources/posts')->assertForbidden()->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_a_resource_without_policy_is_denied(): void
    {
        $this->getJson(self::API.'/resources/tags')->assertForbidden();
    }

    public function test_allow_without_policies(): void
    {
        Facade::allowWithoutPolicies();

        $this->getJson(self::API.'/resources/tags')->assertOk();
    }
}
