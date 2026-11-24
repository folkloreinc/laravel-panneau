<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Panneau\Actions\Action;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionScope;
use Panneau\Actions\ActionValues;
use Panneau\Agents\AgentCaller;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\RunAction;
use Panneau\Events\AgentToolCalled;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Models\Document;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\DocumentsTrashResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Policies\PostPolicy;

/**
 * `run_action`, the tool that runs the actions of a resource, and asks for a confirmation
 * before the ones that need it.
 */
class RunActionTest extends HttpTestCase
{
    private AgentCaller $agent;

    protected function setUp(): void
    {
        parent::setUp();

        RecordAgent::$agents = [];
        $this->agent = new AgentCaller('7', 'Assistant');
        Panneau::allowWithoutPolicies();
        Panneau::resources([ActionPostsResource::class, DocumentsTrashResource::class]);
    }

    public function test_an_action_without_a_confirmation_runs_at_once(): void
    {
        $post = $this->createPost();

        $result = $this->data(['resource' => 'action-posts', 'action' => 'feature-posts', 'selection' => ['ids' => [(string) $post->id]]]);

        $this->assertSame(['succeeded' => 1, 'failed' => 0, 'skipped' => 0], $result['summary']);
        $this->assertTrue((bool) $post->refresh()->featured);
    }

    public function test_a_dry_run_counts_and_runs_nothing(): void
    {
        $ids = [(string) $this->createPost()->id, (string) $this->createPost()->id];

        $archive = $this->data(['resource' => 'action-posts', 'action' => 'archive-posts', 'selection' => ['ids' => $ids], 'dryRun' => true]);
        // An action that needs a confirmation answers its dry run without asking
        $delete = $this->data(['resource' => 'action-posts', 'action' => 'delete', 'selection' => ['ids' => $ids], 'dryRun' => true]);

        $this->assertSame(['succeeded' => 2, 'failed' => 0, 'skipped' => 0], $archive['summary']);
        $this->assertSame(['succeeded' => 2, 'failed' => 0, 'skipped' => 0], $delete['summary']);
        $this->assertSame(2, Post::query()->where('status', 'draft')->count());
        $this->assertErrors(['confirmation'], $this->callTool(['resource' => 'action-posts', 'action' => 'delete', 'selection' => ['ids' => $ids], 'dryRun' => true, 'confirmation' => 'abc']));
    }

    public function test_an_action_that_answers_dry_runs_asks_first_with_its_dry_run(): void
    {
        $ids = [(string) $this->createPost()->id, (string) $this->createPost(['title' => 'Locked'])->id];
        $arguments = ['resource' => 'action-posts', 'action' => 'delete', 'selection' => ['ids' => $ids]];

        $asked = $this->data($arguments);

        $this->assertSame('confirmation_required', $asked['status']);
        $this->assertSame('Delete 2 items? This can\'t be undone.', $asked['message']);
        $this->assertSame(['succeeded' => 1, 'failed' => 0, 'skipped' => 1], $asked['preview']['summary']);
        $this->assertSame(2, Post::query()->count());

        $result = $this->data([...$arguments, 'confirmation' => $asked['confirmation']]);

        $this->assertSame(['succeeded' => 1, 'failed' => 0, 'skipped' => 1], $result['summary']);
        $this->assertSame(['Locked'], Post::query()->pluck('title')->all());
    }

    public function test_an_action_without_dry_runs_asks_first_with_a_count(): void
    {
        $first = $this->createPost();
        $this->createPost();
        $arguments = ['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => ['ids' => [(string) $first->id]], 'values' => ['notify' => true]];

        $asked = $this->data($arguments);

        // The action's own message
        $this->assertSame('Publish 1 post?', $asked['message']);
        $this->assertSame(['count' => 1], $asked['preview']);
        $this->assertSame(['count' => 2], $this->data(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => ['query' => ['filter' => []]]])['preview']);
        $this->assertSame(0, Post::query()->where('status', 'published')->count());

        $this->data([...$arguments, 'confirmation' => $asked['confirmation']]);

        $this->assertSame('published', $first->refresh()->status);
        $this->assertSame('Notified', $first->body);
        $this->assertSame(1, Post::query()->where('status', 'published')->count());
    }

    public function test_the_default_message_says_when_an_action_can_not_be_undone(): void
    {
        $ids = [(string) $this->createPost()->id];
        $message = fn (string $action): string => $this->data(['resource' => 'action-posts', 'action' => $action, 'selection' => ['ids' => $ids]])['message'];

        $this->assertSame('Delete 1 item? This can\'t be undone.', $message('delete'));
        $this->assertSame('Run “Purge posts” on 1 item? This can\'t be undone.', $message('purge-posts'));
        // An action that isn't destructive keeps the sentence with its label
        $this->assertSame('Run “Review posts” on 1 item?', $message('review-posts'));
        $this->assertSame(1, Post::query()->count());
    }

    public function test_a_confirmation_is_refused_for_other_arguments(): void
    {
        $post = $this->createPost();
        $other = $this->createPost();
        $arguments = ['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => ['ids' => [(string) $post->id]], 'values' => ['notify' => true, 'note' => 'Hi']];
        $token = $this->data($arguments, $this->agent)['confirmation'];
        $tampered = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');

        $refused = [
            'a character changed' => [[...$arguments, 'confirmation' => $tampered], 1, $this->agent],
            'another user' => [[...$arguments, 'confirmation' => $token], 2, $this->agent],
            'another connection' => [[...$arguments, 'confirmation' => $token], 1, new AgentCaller('8')],
            'no connection' => [[...$arguments, 'confirmation' => $token], 1, null],
            'another resource' => [[...$arguments, 'resource' => 'posts', 'confirmation' => $token], 1, $this->agent],
            'another action' => [[...$arguments, 'action' => 'delete', 'values' => [], 'confirmation' => $token], 1, $this->agent],
            'another selection' => [[...$arguments, 'selection' => ['ids' => [(string) $other->id]], 'confirmation' => $token], 1, $this->agent],
            'other values' => [[...$arguments, 'values' => ['notify' => false, 'note' => 'Hi'], 'confirmation' => $token], 1, $this->agent],
        ];
        foreach ($refused as $case => [$call, $user, $agent]) {
            $result = $this->callTool($call, $agent, $user);
            $this->assertSame(422, $result->status(), $case);
            $this->assertSame(['confirmation'], array_keys($result->problem['errors'] ?? []), $case);
        }
        $this->travel(6)->minutes();
        $this->assertErrors(['confirmation'], $this->callTool([...$arguments, 'confirmation' => $token], $this->agent));
        $this->travelBack();
        $this->assertSame(0, Post::query()->where('status', 'published')->count());

        // The same arguments in another order
        $this->data(['values' => ['note' => 'Hi', 'notify' => true], 'confirmation' => $token, ...$arguments], $this->agent);
        $this->assertSame('published', $post->refresh()->status);
    }

    public function test_an_action_without_a_confirmation_refuses_one(): void
    {
        $post = $this->createPost();

        $this->assertErrors(['confirmation'], $this->callTool(['resource' => 'action-posts', 'action' => 'feature-posts', 'selection' => ['ids' => [(string) $post->id]], 'confirmation' => 'abc']));
        $this->assertFalse((bool) $post->refresh()->featured);
    }

    public function test_duplicate_answers_the_copy(): void
    {
        $post = $this->createPost(['title' => 'Original']);
        $other = $this->createPost();

        $result = $this->data(['resource' => 'action-posts', 'action' => 'duplicate', 'selection' => ['ids' => [(string) $post->id]]]);

        $this->assertSame(['succeeded' => 1, 'failed' => 0, 'skipped' => 0], $result['summary']);
        $this->assertSame('Original (copy)', $result['item']['title']);
        $this->assertIsString($result['item']['version']);
        $this->assertSame(3, Post::query()->count());
        $this->assertErrors(['selection'], $this->callTool(['resource' => 'action-posts', 'action' => 'duplicate', 'selection' => ['ids' => [(string) $post->id, (string) $other->id]]]));
        $this->assertErrors(['selection'], $this->callTool(['resource' => 'action-posts', 'action' => 'duplicate', 'selection' => ['query' => ['filter' => []]]]));
        $this->assertSame(3, Post::query()->count());
    }

    public function test_delete_with_a_trash_runs_at_once_and_restore_puts_the_items_back(): void
    {
        $document = Document::query()->create(['name' => 'First']);
        $selection = ['ids' => [$document->uid]];

        $deleted = $this->data(['resource' => 'documents', 'action' => 'delete', 'selection' => $selection]);

        $this->assertSame(['action' => 'restore', 'items' => [$document->uid]], $deleted['undo']);
        $this->assertSame(0, Document::query()->count());

        $this->data(['resource' => 'documents', 'action' => 'restore', 'selection' => $selection]);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_the_action_reads_the_agent_in_its_context(): void
    {
        $post = $this->createPost();

        $this->data(['resource' => 'action-posts', 'action' => 'record-agent', 'selection' => ['ids' => [(string) $post->id]]], $this->agent);
        $this->postJson(self::API.'/resources/action-posts/actions/record-agent', ['selection' => ['ids' => [(string) $post->id]]])->assertOk();

        $this->assertSame([$this->agent, null], RecordAgent::$agents);
    }

    public function test_agent_tool_called_for_each_call_that_ran(): void
    {
        Event::fake([AgentToolCalled::class]);
        $ids = [(string) $this->createPost()->id, (string) $this->createPost()->id];

        $this->data(['resource' => 'action-posts', 'action' => 'feature-posts', 'selection' => ['ids' => $ids]], $this->agent);
        $this->data(['resource' => 'action-posts', 'action' => 'delete', 'selection' => ['ids' => $ids], 'dryRun' => true], $this->agent);
        $asked = $this->data(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => ['ids' => [$ids[0]]]], $this->agent);
        Event::assertDispatchedTimes(AgentToolCalled::class, 1);

        $this->data(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => ['ids' => [$ids[0]]], 'confirmation' => $asked['confirmation']], $this->agent);
        $copy = $this->data(['resource' => 'action-posts', 'action' => 'duplicate', 'selection' => ['ids' => [$ids[1]]]], $this->agent)['item']['id'];

        $items = [];
        Event::assertDispatched(AgentToolCalled::class, function (AgentToolCalled $event) use (&$items): bool {
            $this->assertSame($this->agent, $event->agent);
            $this->assertSame('run_action', $event->tool);
            $this->assertSame('action-posts', $event->resource);
            $items[] = $event->items;

            return true;
        });
        $this->assertSame([$ids, [$ids[0]], [(string) $copy]], $items);
    }

    public function test_the_errors_of_the_operation(): void
    {
        $post = $this->createPost();
        $selection = ['ids' => [(string) $post->id]];

        // A user who can't run the action, before and after the confirmation
        $this->assertSame(403, $this->callTool(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => $selection], null, null)->status());
        PostPolicy::$denied = ['update'];
        $this->assertSame(403, $this->callTool(['resource' => 'action-posts', 'action' => 'feature-posts', 'selection' => $selection])->status());
        PostPolicy::$denied = [];
        $this->assertSame(404, $this->callTool(['resource' => 'action-posts', 'action' => 'unknown', 'selection' => $selection])->status());
        $this->assertSame(404, $this->callTool(['resource' => 'unknown', 'action' => 'delete', 'selection' => $selection])->status());

        $this->app['config']->set('panneau.actions.sync_limit', 1);
        $this->createPost();
        $query = ['query' => ['filter' => []]];
        $this->assertErrors(['selection'], $this->callTool(['resource' => 'action-posts', 'action' => 'feature-posts', 'selection' => $query]));
        $this->assertErrors(['selection'], $this->callTool(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => $query]));

        // Invalid values, before the confirmation is asked
        $this->assertErrors(['note'], $this->callTool(['resource' => 'action-posts', 'action' => 'publish-posts', 'selection' => $selection, 'values' => ['note' => str_repeat('a', 11)]]));
        $this->assertErrors(['reason'], $this->callTool(['resource' => 'action-posts', 'action' => 'archive-posts', 'selection' => $selection]));
        $this->assertSame('draft', $post->refresh()->status);
    }

    public function test_the_tool_describes_itself(): void
    {
        $tool = $this->app->make(RunAction::class);

        $this->assertSame('run_action', $tool->name());
        $this->assertFalse($tool->readOnly());
        $this->assertSame(['resource', 'action', 'selection', 'values', 'dryRun', 'confirmation'], array_keys($tool->inputSchema()['properties']));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(array $arguments, ?AgentCaller $agent = null, ?int $user = 1): ToolResult
    {
        $user = $user === null ? null : new GenericUser(['id' => $user, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): ?GenericUser => $user);

        return $this->app->make(RunAction::class)->handle(new ToolCall($user, $arguments, $request, $agent));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function data(array $arguments, ?AgentCaller $agent = null): array
    {
        $result = $this->callTool($arguments, $agent);
        $this->assertFalse($result->isError(), (string) json_encode($result->problem));

        return $result->data ?? [];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function assertErrors(array $arguments, ToolResult $result): void
    {
        $this->assertSame(422, $result->status(), (string) json_encode($result->problem));
        $this->assertSame($arguments, array_keys($result->problem['errors'] ?? []));
    }
}

/**
 * The posts, with an action that records the agent of its context.
 */
class ActionPostsResource extends PostsResource
{
    public function id(): string
    {
        return 'action-posts';
    }

    public function actions(): array
    {
        return [...parent::actions(), RecordAgent::class, PurgePosts::class, ReviewPosts::class];
    }
}

class RecordAgent extends Action
{
    /**
     * @var list<AgentCaller|null>
     */
    public static array $agents = [];

    public function scope(): array
    {
        return [ActionScope::Item];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        self::$agents[] = $this->context()->agent;

        return $result;
    }
}

/**
 * An action that destroys what can't be undone, without a message of its own.
 */
class PurgePosts extends Action
{
    public function destructive(): bool
    {
        return true;
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        $items->each(fn (Post $post): ?bool => $post->delete());

        return $result;
    }
}

/**
 * An action that asks first, without a message of its own.
 */
class ReviewPosts extends Action
{
    public function confirm(): bool
    {
        return true;
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        return $result;
    }
}
