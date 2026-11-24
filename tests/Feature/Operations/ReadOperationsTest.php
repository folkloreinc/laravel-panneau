<?php

namespace Panneau\Tests\Feature\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Panneau\Operations\ListItems;
use Panneau\Operations\Search;
use Panneau\Operations\ShowItem;
use Panneau\Tests\Feature\Http\HttpTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TestApp\Policies\PostPolicy;

/**
 * The read operations, called without a route.
 */
class ReadOperationsTest extends HttpTestCase
{
    private GenericUser $user;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $this->request = Request::create('/');
        $this->request->setUserResolver(fn (): GenericUser => $this->user);
    }

    public function test_list_items_filters_sorts_and_restricts_the_fields(): void
    {
        $this->createPost(['title' => 'B summer', 'status' => 'published']);
        $this->createPost(['title' => 'A summer', 'status' => 'published']);
        $this->createPost(['title' => 'C summer', 'status' => 'draft']);
        $this->createPost(['title' => 'Elsewhere', 'status' => 'published', 'team_id' => 2]);

        $result = $this->app->make(ListItems::class)->handle($this->user, 'posts', [
            'filter' => ['status' => ['published']],
            'sort' => 'title',
            'fields' => 'title',
        ], $this->request);

        $this->assertSame(['A summer', 'B summer'], array_column($result['data'], 'title'));
        $this->assertSame(['id', 'version', 'abilities', 'title'], array_keys($result['data'][0]));
        $this->assertSame(['page' => 1, 'perPage' => 20, 'total' => 2, 'lastPage' => 1], $result['meta']);
    }

    public function test_list_items_is_refused_without_view_any(): void
    {
        PostPolicy::$denied = ['viewAny'];

        $this->expectException(AuthorizationException::class);
        $this->app->make(ListItems::class)->handle($this->user, 'posts', [], $this->request);
    }

    public function test_list_items_of_an_unknown_resource_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->app->make(ListItems::class)->handle($this->user, 'unknown', [], $this->request);
    }

    public function test_show_item_with_fields_and_its_version(): void
    {
        $post = $this->createPost(['title' => 'Hello']);

        $result = $this->app->make(ShowItem::class)->handle($this->user, 'posts', (string) $post->getKey(), $this->request, ['title']);

        $this->assertSame(['id', 'version', 'abilities', 'title'], array_keys($result['data']));
        $this->assertSame('Hello', $result['data']['title']);
        $this->assertIsString($result['version']);
        $this->assertSame($result['data']['version'], $result['version']);
    }

    public function test_show_item_out_of_the_scope_is_not_found(): void
    {
        $post = $this->createPost(['team_id' => 2]);

        $this->expectException(NotFoundHttpException::class);
        $this->app->make(ShowItem::class)->handle($this->user, 'posts', (string) $post->getKey(), $this->request);
    }

    public function test_show_item_is_refused_without_view(): void
    {
        $post = $this->createPost();
        PostPolicy::$denied = ['view'];

        $this->expectException(AuthorizationException::class);
        $this->app->make(ShowItem::class)->handle($this->user, 'posts', (string) $post->getKey(), $this->request);
    }

    public function test_search(): void
    {
        $this->createPost(['title' => 'A summer']);
        $this->createPost(['title' => 'Winter']);
        $this->createPost(['title' => 'Another summer', 'team_id' => 2]);

        $result = $this->app->make(Search::class)->handle($this->user, ['q' => 'summer', 'resources' => 'posts'], $this->request);

        $this->assertCount(1, $result['data']);
        $this->assertSame('posts', $result['data'][0]['resource']);
        $this->assertSame(['A summer'], array_column($result['data'][0]['items'], 'title'));
        $this->assertSame(1, $result['data'][0]['total']);
    }
}
