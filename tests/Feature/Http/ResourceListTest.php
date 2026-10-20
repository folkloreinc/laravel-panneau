<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Http\Problem;
use Panneau\Support\Facade;
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

    public function test_items_have_an_id_a_version_and_abilities(): void
    {
        $post = $this->createPost(['title' => 'Locked', 'secret' => 'hidden']);

        $item = $this->getJson(self::API.'/resources/posts')->json('data.0');

        $this->assertSame((string) $post->id, $item['id']);
        $this->assertIsString($item['version']);
        $this->assertSame(
            ['view' => true, 'update' => false, 'delete' => false, 'publish-posts' => true, 'archive-posts' => false],
            $item['abilities'],
        );
        $this->assertArrayNotHasKey('secret', $item);
        $this->assertArrayNotHasKey('team_id', $item);
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
