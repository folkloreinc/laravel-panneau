<?php

namespace Panneau\Tests\Feature\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Operations\RunAction;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Models\Post;

/**
 * The `RunAction` operation, called without a route: it reads the user and the payload of its
 * arguments, never those of the request.
 */
class RunActionTest extends HttpTestCase
{
    private GenericUser $user;

    private Post $decoy;

    /** A request of a guest, with a body that isn't the operation's. */
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $this->decoy = $this->createPost(['title' => 'From the request']);
        $this->request = Request::create('/', 'POST', [
            'selection' => ['ids' => [(string) $this->decoy->id]],
            'dryRun' => true,
        ]);
        $this->request->setUserResolver(fn (): ?GenericUser => null);
    }

    public function test_runs_the_action_with_the_payload_as_the_user(): void
    {
        $post = $this->createPost(['title' => 'From the payload']);

        $result = $this->app->make(RunAction::class)->handle($this->user, 'posts', 'publish-posts', [
            'selection' => ['ids' => [(string) $post->id, '999']],
            'values' => ['notify' => true],
        ], $this->request);

        $this->assertSame(['succeeded' => 1, 'failed' => 0, 'skipped' => 1], $result['summary']);
        $this->assertSame('published', $post->refresh()->status);
        $this->assertSame('Notified', $post->body);
        $this->assertSame('draft', $this->decoy->refresh()->status);
    }

    public function test_a_dry_run_counts_without_running(): void
    {
        $post = $this->createPost();
        $locked = $this->createPost(['title' => 'Locked']);

        $result = $this->app->make(RunAction::class)->handle($this->user, 'posts', 'delete', [
            'selection' => ['ids' => [(string) $post->id, (string) $locked->id]],
            'dryRun' => true,
        ], $this->request);

        $this->assertSame([
            'summary' => ['succeeded' => 1, 'failed' => 0, 'skipped' => 1],
            'results' => [['id' => (string) $locked->id, 'status' => 'skipped', 'reason' => 'forbidden']],
        ], $result);
        $this->assertSame(3, Post::query()->count());
    }

    public function test_a_selection_over_the_limit_is_refused(): void
    {
        $this->app['config']->set('panneau.actions.sync_limit', 1);
        $first = $this->createPost();
        $second = $this->createPost();

        try {
            $this->app->make(RunAction::class)->handle($this->user, 'posts', 'publish-posts', [
                'selection' => ['ids' => [(string) $first->id, (string) $second->id]],
            ], $this->request);
            $this->fail('No validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selection', $exception->errors());
        }
        $this->assertSame(0, Post::query()->where('status', 'published')->count());
    }

    public function test_a_user_who_cannot_run_the_action_is_refused(): void
    {
        $post = $this->createPost();
        $this->request->setUserResolver(fn (): GenericUser => $this->user);

        $this->expectException(AuthorizationException::class);
        try {
            $this->app->make(RunAction::class)->handle(null, 'posts', 'publish-posts', [
                'selection' => ['ids' => [(string) $post->id]],
            ], $this->request);
        } finally {
            $this->assertSame('draft', $post->refresh()->status);
        }
    }
}
