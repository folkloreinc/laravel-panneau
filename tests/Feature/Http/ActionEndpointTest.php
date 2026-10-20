<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Testing\TestResponse;
use Panneau\Http\Problem;
use TestApp\Models\Post;
use TestApp\Policies\PostPolicy;

class ActionEndpointTest extends HttpTestCase
{
    private function runAction(string $action, array $payload): TestResponse
    {
        return $this->postJson(self::API.'/resources/posts/actions/'.$action, $payload);
    }

    public function test_a_selection_of_ids(): void
    {
        $first = $this->createPost(['title' => 'First']);
        $second = $this->createPost(['title' => 'Second']);
        $archived = $this->createPost(['title' => 'Archived', 'status' => 'archived']);
        $other = $this->createPost(['title' => 'Other team', 'team_id' => 2]);

        $response = $this->runAction('publish-posts', [
            'selection' => ['ids' => [(string) $first->id, (string) $second->id, (string) $archived->id, (string) $other->id, '999']],
            'values' => ['notify' => true],
        ])->assertOk();

        $response->assertExactJson([
            'message' => '2 posts published.',
            'summary' => ['succeeded' => 2, 'failed' => 0, 'skipped' => 3],
            'results' => [
                ['id' => (string) $archived->id, 'status' => 'skipped', 'reason' => 'forbidden'],
                ['id' => (string) $other->id, 'status' => 'skipped', 'reason' => 'not_found'],
                ['id' => '999', 'status' => 'skipped', 'reason' => 'not_found'],
            ],
            'refresh' => 'items',
        ]);
        $this->assertSame('published', $first->refresh()->status);
        $this->assertSame('Notified', $first->body);
        $this->assertSame('archived', $archived->refresh()->status);
        $this->assertSame('draft', $other->refresh()->status);
    }

    public function test_failed_items_are_reported(): void
    {
        $post = $this->createPost(['title' => '']);
        $ok = $this->createPost(['title' => 'Ok']);

        $this->runAction('publish-posts', ['selection' => ['ids' => [(string) $post->id, (string) $ok->id]]])
            ->assertOk()
            ->assertJsonPath('summary', ['succeeded' => 1, 'failed' => 1, 'skipped' => 0])
            ->assertJsonPath('results', [['id' => (string) $post->id, 'status' => 'failed', 'message' => 'The title is required.']]);
    }

    public function test_a_selection_by_query(): void
    {
        $draft = $this->createPost(['title' => 'Summer draft', 'status' => 'draft']);
        $excluded = $this->createPost(['title' => 'Summer excluded', 'status' => 'draft']);
        $this->createPost(['title' => 'Winter draft', 'status' => 'draft']);
        $this->createPost(['title' => 'Summer published', 'status' => 'published']);
        $this->createPost(['title' => 'Summer other team', 'status' => 'draft', 'team_id' => 2]);

        $this->runAction('publish-posts', [
            'selection' => [
                'query' => ['filter' => ['status' => 'draft', 'team_id' => 2], 'q' => 'summer'],
                'except' => [(string) $excluded->id],
            ],
        ])->assertOk()->assertJsonPath('summary.succeeded', 1);

        $this->assertSame('published', $draft->refresh()->status);
        $this->assertSame('draft', $excluded->refresh()->status);
        $this->assertSame(1, Post::query()->where('team_id', 2)->where('status', 'draft')->count());
    }

    public function test_a_selection_by_query_with_a_date_range(): void
    {
        $january = $this->createPost(['status' => 'draft', 'published_at' => '2026-01-31 12:00:00']);
        $february = $this->createPost(['status' => 'draft', 'published_at' => '2026-02-01 12:00:00']);

        $this->runAction('publish-posts', [
            'selection' => ['query' => ['filter' => ['published_at' => ['from' => '2026-01-01', 'to' => '2026-01-31']]]],
        ])->assertOk()->assertJsonPath('summary.succeeded', 1);

        $this->assertSame('published', $january->refresh()->status);
        $this->assertSame('draft', $february->refresh()->status);
    }

    public function test_an_empty_query_selects_everything_in_the_scope(): void
    {
        $this->createPost();
        $this->createPost();
        $this->createPost(['team_id' => 2]);

        $this->runAction('publish-posts', ['selection' => ['query' => []]])->assertOk()->assertJsonPath('summary.succeeded', 2);
    }

    public function test_a_single_item_that_is_missing_or_forbidden_is_an_error(): void
    {
        $archived = $this->createPost(['status' => 'archived']);

        $this->runAction('publish-posts', ['selection' => ['ids' => ['999']]])->assertNotFound()->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->runAction('publish-posts', ['selection' => ['ids' => [(string) $archived->id]]])->assertForbidden();
    }

    public function test_invalid_values_are_a_422_problem(): void
    {
        $post = $this->createPost();

        $this->runAction('publish-posts', ['selection' => ['ids' => [(string) $post->id]], 'values' => ['note' => 'Far too long']])
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('errors.note.0', fn (string $message): bool => $message !== '');
        $this->assertSame('draft', $post->refresh()->status);
    }

    public function test_invalid_selections_are_refused(): void
    {
        $this->runAction('publish-posts', [])->assertStatus(422)->assertJsonStructure(['errors' => ['selection']]);
        $this->runAction('publish-posts', ['selection' => ['ids' => []]])->assertStatus(422);
        $this->runAction('publish-posts', ['selection' => []])->assertStatus(422);
        $this->runAction('publish-posts', ['selection' => ['ids' => [['nested']]]])->assertStatus(422);
    }

    public function test_selections_above_the_limit_are_refused(): void
    {
        $this->app['config']->set('panneau.actions.sync_limit', 2);
        foreach (range(1, 3) as $index) {
            $this->createPost();
        }

        $this->runAction('publish-posts', ['selection' => ['query' => []]])->assertStatus(422)->assertJsonStructure(['errors' => ['selection']]);
        $this->runAction('publish-posts', ['selection' => ['ids' => ['1', '2', '3']]])->assertStatus(422);
        $this->assertSame(0, Post::query()->where('status', 'published')->count());
    }

    public function test_unknown_and_client_side_actions_are_not_found(): void
    {
        $this->runAction('unknown', ['selection' => ['ids' => ['1']]])->assertNotFound();
        $this->runAction('preview', ['selection' => ['ids' => ['1']]])->assertNotFound();
        $this->runAction('copy-link', ['selection' => ['ids' => ['1']]])->assertNotFound();
    }

    public function test_view_any_is_required(): void
    {
        PostPolicy::$denied = ['viewAny'];

        $this->runAction('publish-posts', ['selection' => ['query' => []]])->assertForbidden();
    }

    public function test_the_delete_action(): void
    {
        $first = $this->createPost();
        $locked = $this->createPost(['title' => 'Locked']);
        $second = $this->createPost();

        $this->runAction('delete', ['selection' => ['ids' => [(string) $first->id, (string) $locked->id, (string) $second->id]]])
            ->assertOk()
            ->assertExactJson([
                'summary' => ['succeeded' => 2, 'failed' => 0, 'skipped' => 1],
                'results' => [['id' => (string) $locked->id, 'status' => 'skipped', 'reason' => 'forbidden']],
                'refresh' => 'list',
            ]);

        $this->assertSame([$locked->id], Post::query()->pluck('id')->all());
    }

    public function test_the_delete_action_is_declared_in_the_definition(): void
    {
        $actions = array_column($this->getJson(self::API.'/definition')->json('resources.0.actions'), null, 'id');

        $this->assertSame([
            'id' => 'delete',
            'label' => 'Delete',
            'icon' => 'trash',
            'kind' => 'server',
            'scope' => ['item', 'selection'],
            'destructive' => true,
            'confirm' => true,
            'abilities' => ['run' => true],
        ], $actions['delete']);
    }
}
