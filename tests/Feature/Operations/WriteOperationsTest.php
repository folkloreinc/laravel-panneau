<?php

namespace Panneau\Tests\Feature\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Panneau\Exceptions\VersionConflictException;
use Panneau\Operations\CreateItem;
use Panneau\Operations\DeleteItem;
use Panneau\Operations\DuplicateItem;
use Panneau\Operations\UpdateItem;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Models\Post;

/**
 * The write operations, called without a route: they read the user and the values of their
 * arguments, never those of the request.
 */
class WriteOperationsTest extends HttpTestCase
{
    private GenericUser $user;

    /** A request of a guest, with values that aren't the operation's. */
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $this->request = Request::create('/', 'POST', ['title' => 'From the request', 'status' => 'published']);
        $this->request->setUserResolver(fn (): ?GenericUser => null);
    }

    public function test_create_item_saves_the_values_as_the_user(): void
    {
        $result = $this->app->make(CreateItem::class)->handle($this->user, 'posts', ['title' => 'From the values', 'status' => 'draft', 'preview_password' => 'preview-123'], $this->request);

        $this->assertInstanceOf(Post::class, $result['item']);
        $post = Post::query()->sole();
        $this->assertSame('From the values', $post->title);
        $this->assertSame('draft', $post->status);
        $this->assertSame(1, $post->team_id);
    }

    public function test_create_item_is_refused_without_a_user(): void
    {
        $this->request->setUserResolver(fn (): GenericUser => $this->user);

        $this->expectException(AuthorizationException::class);
        try {
            $this->app->make(CreateItem::class)->handle(null, 'posts', ['title' => 'New', 'status' => 'draft'], $this->request);
        } finally {
            $this->assertSame(0, Post::query()->count());
        }
    }

    public function test_update_item_saves_the_values_as_the_user(): void
    {
        $post = $this->createPost(['title' => 'Before']);

        $result = $this->app->make(UpdateItem::class)->handle($this->user, 'posts', (string) $post->id, ['title' => 'After'], null, $this->request);

        $this->assertSame('After', $result['item']->title);
        $this->assertSame('After', $post->refresh()->title);
        $this->assertNotSame('published', $post->status);
    }

    public function test_update_item_with_a_stale_version_is_a_conflict(): void
    {
        $post = $this->createPost(['title' => 'Before']);

        try {
            $this->app->make(UpdateItem::class)->handle($this->user, 'posts', (string) $post->id, ['title' => 'After'], '"stale"', $this->request);
            $this->fail('No conflict.');
        } catch (VersionConflictException $exception) {
            $this->assertSame('Before', $exception->current['title']);
            $this->assertTrue($exception->current['abilities']['update']);
        }
        $this->assertSame('Before', $post->refresh()->title);
    }

    public function test_duplicate_item_as_the_user(): void
    {
        $post = $this->createPost(['title' => 'Original']);

        $copy = $this->app->make(DuplicateItem::class)->handle($this->user, 'posts', (string) $post->id, $this->request);

        $this->assertInstanceOf(Post::class, $copy);
        $this->assertNotSame($post->id, $copy->id);
        $this->assertSame('Original (copy)', $copy->title);
    }

    public function test_delete_item_as_the_user(): void
    {
        $post = $this->createPost();

        $this->app->make(DeleteItem::class)->handle($this->user, 'posts', (string) $post->id, $this->request);

        $this->assertNull(Post::query()->find($post->id));
    }

    public function test_delete_item_is_refused_without_a_user(): void
    {
        $post = $this->createPost();
        $this->request->setUserResolver(fn (): GenericUser => $this->user);

        $this->expectException(AuthorizationException::class);
        try {
            $this->app->make(DeleteItem::class)->handle(null, 'posts', (string) $post->id, $this->request);
        } finally {
            $this->assertNotNull(Post::query()->find($post->id));
        }
    }
}
