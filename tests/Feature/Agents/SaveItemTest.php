<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Panneau\Agents\AgentCaller;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\GetItem;
use Panneau\Agents\Tools\SaveItem;
use Panneau\Events\AgentToolCalled;
use Panneau\Fields\Boolean;
use Panneau\Fields\Field;
use Panneau\Fields\Text;
use Panneau\Forms\SaveContext;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\HttpTestCase;
use RuntimeException;
use TestApp\Models\Entry;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\EntriesResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Policies\PostPolicy;
use Throwable;

/**
 * `save_item`, the tool that creates and changes items, called as its user.
 */
class SaveItemTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingText::$agents = [];
        Panneau::allowWithoutPolicies();
        Panneau::resources([AgentPostsResource::class, EntriesResource::class]);
    }

    public function test_it_creates_an_item(): void
    {
        $item = $this->data(['resource' => 'agent-posts', 'values' => ['title' => 'Hello']]);

        $this->assertSame('Hello', $item['title']);
        $this->assertIsString($item['version']);
        $this->assertSame('Hello', Post::query()->findOrFail($item['id'])->title);
        // As `get_item` gives it
        $this->assertEquals($this->app->make(GetItem::class)->handle($this->toolCall(['resource' => 'agent-posts', 'id' => (string) $item['id']]))->data, $item);
    }

    public function test_it_creates_an_item_of_a_type(): void
    {
        $item = $this->data(['resource' => 'entries', 'type' => 'article', 'values' => ['title' => 'News']]);

        $this->assertSame('article', $item['type']);
        $this->assertSame('article', Entry::query()->findOrFail($item['id'])->type);
        $this->assertErrors(['type'], $this->callTool(['resource' => 'entries', 'type' => 'unknown', 'values' => ['title' => 'News']]));
        $this->assertErrors(['type'], $this->callTool(['resource' => 'entries', 'values' => ['title' => 'News']]));
    }

    public function test_it_updates_an_item_at_its_version(): void
    {
        $post = $this->createPost(['title' => 'Before', 'seo' => ['title' => 'Before']]);
        $version = $this->version($post);

        $item = $this->data(['resource' => 'agent-posts', 'id' => (string) $post->getKey(), 'version' => $version, 'values' => ['title' => 'After']]);

        $this->assertSame('After', $item['title']);
        $this->assertSame('Before', $item['seo']['title'] ?? null);
        $this->assertSame('After', $post->fresh()?->title);
    }

    public function test_a_stale_version_answers_the_current_item(): void
    {
        $post = $this->createPost(['title' => 'Current']);

        foreach (['1999-01-01 00:00:00', null, '*'] as $version) {
            $result = $this->callTool(['resource' => 'agent-posts', 'id' => (string) $post->getKey(), 'version' => $version, 'values' => ['title' => 'Lost']]);

            $this->assertSame(412, $result->status(), (string) json_encode($result->problem));
            $this->assertSame('Current', $result->problem['current']['title'] ?? null);
        }
        $this->assertSame('Current', $post->fresh()?->title);
    }

    public function test_the_arguments_that_do_not_go_together(): void
    {
        $post = $this->createPost();
        $id = (string) $post->getKey();

        $result = $this->callTool(['resource' => 'agent-posts', 'id' => $id, 'values' => ['title' => 'No version']]);
        $this->assertErrors(['version'], $result);
        $this->assertSame(['Changing an item needs the `version` it was read at.'], $result->problem['errors']['version']);
        $this->assertErrors(['version'], $this->callTool(['resource' => 'agent-posts', 'version' => 'abc', 'values' => ['title' => 'New']]));
        $this->assertErrors(['version'], $this->callTool(['resource' => 'agent-posts', 'version' => null, 'values' => ['title' => 'New']]));
        $this->assertErrors(['type'], $this->callTool(['resource' => 'agent-posts', 'id' => $id, 'version' => $this->version($post), 'type' => 'article', 'values' => []]));
        $this->assertErrors(['values'], $this->callTool(['resource' => 'agent-posts']));
        $this->assertErrors(['other'], $this->callTool(['resource' => 'agent-posts', 'values' => [], 'other' => true]));
        $this->assertSame(1, Post::query()->count());
    }

    public function test_a_field_the_user_can_not_edit_is_ignored(): void
    {
        $item = $this->data(['resource' => 'agent-posts', 'values' => ['title' => 'Hello', 'featured' => true]]);

        $this->assertFalse((bool) Post::query()->findOrFail($item['id'])->featured);
    }

    public function test_a_user_who_can_not_create_or_update_is_forbidden(): void
    {
        $post = $this->createPost();
        PostPolicy::$denied = ['create', 'update'];

        $this->assertSame(403, $this->callTool(['resource' => 'agent-posts', 'values' => ['title' => 'New']])->status());
        $this->assertSame(403, $this->callTool(['resource' => 'agent-posts', 'id' => (string) $post->getKey(), 'version' => $this->version($post), 'values' => ['title' => 'Changed']])->status());
        $this->assertSame(1, Post::query()->count());
        $this->assertSame('A post', $post->fresh()?->title);
    }

    public function test_invalid_values_are_errors_by_field(): void
    {
        $this->assertErrors(['title'], $this->callTool(['resource' => 'agent-posts', 'values' => ['title' => str_repeat('a', 121)]]));
        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_agent_reaches_the_fields(): void
    {
        $agent = new AgentCaller('7', 'Assistant');
        $post = $this->createPost();

        $this->data(['resource' => 'agent-posts', 'values' => ['title' => 'Created']], $agent);
        $this->data(['resource' => 'agent-posts', 'id' => (string) $post->getKey(), 'version' => $this->version($post), 'values' => ['title' => 'Updated']], $agent);

        $this->assertSame([$agent, $agent], RecordingText::$agents);
    }

    public function test_the_api_saves_without_an_agent(): void
    {
        $this->postJson(self::API.'/resources/agent-posts', ['title' => 'Through the API'])->assertCreated();

        $this->assertSame([null], RecordingText::$agents);
    }

    public function test_a_save_dispatches_agent_tool_called(): void
    {
        Event::fake([AgentToolCalled::class]);
        $agent = new AgentCaller('7', 'Assistant');

        $item = $this->data(['resource' => 'agent-posts', 'values' => ['title' => 'Hello']], $agent);

        Event::assertDispatchedTimes(AgentToolCalled::class, 1);
        Event::assertDispatched(AgentToolCalled::class, fn (AgentToolCalled $event): bool => $event->user?->getAuthIdentifier() === 1
            && $event->agent === $agent
            && $event->tool === 'save_item'
            && $event->resource === 'agent-posts'
            && $event->items === [(string) $item['id']]);
    }

    public function test_a_listener_that_throws_does_not_fail_a_save(): void
    {
        $this->withoutExceptionHandling();
        $reported = [];
        $this->app->instance(ExceptionHandler::class, new class($reported) extends Handler
        {
            /** @param  list<Throwable>  $reported */
            public function __construct(private array &$reported) {}

            public function report(Throwable $e): void
            {
                $this->reported[] = $e;
            }
        });
        Event::listen(AgentToolCalled::class, function (): void {
            throw new RuntimeException('The listener failed.');
        });

        $item = $this->data(['resource' => 'agent-posts', 'values' => ['title' => 'Saved once']]);

        $this->assertSame('Saved once', $item['title']);
        $this->assertSame(1, Post::query()->where('title', 'Saved once')->count());
        $this->assertCount(1, $reported);
        $this->assertSame('The listener failed.', $reported[0]->getMessage());
    }

    public function test_a_read_or_a_failed_call_dispatches_nothing(): void
    {
        Event::fake([AgentToolCalled::class]);
        $post = $this->createPost();

        $this->app->make(GetItem::class)->handle($this->toolCall(['resource' => 'agent-posts', 'id' => (string) $post->getKey()]));
        $this->assertTrue($this->callTool(['resource' => 'agent-posts', 'values' => ['title' => '']])->isError());

        Event::assertNotDispatched(AgentToolCalled::class);
    }

    public function test_the_tool_describes_itself(): void
    {
        $tool = $this->app->make(SaveItem::class);

        $this->assertSame('save_item', $tool->name());
        $this->assertFalse($tool->readOnly());
        $this->assertSame(['resource', 'id', 'version', 'type', 'values'], array_keys($tool->inputSchema()['properties']));
        $this->assertFalse($tool->inputSchema()['additionalProperties']);
    }

    private function version(Post $post): string
    {
        $version = $this->app->make(GetItem::class)->handle($this->toolCall(['resource' => 'agent-posts', 'id' => (string) $post->getKey()]))->data['version'] ?? null;
        $this->assertIsString($version);

        return $version;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolCall(array $arguments, ?AgentCaller $agent = null): ToolCall
    {
        $user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): GenericUser => $user);

        return new ToolCall($user, $arguments, $request, $agent);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(array $arguments, ?AgentCaller $agent = null): ToolResult
    {
        return $this->app->make(SaveItem::class)->handle($this->toolCall($arguments, $agent));
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
 * The posts, without the fields required on create, with a title that records the agent of each save, and a `featured` no one can edit.
 */
class AgentPostsResource extends PostsResource
{
    public function fields(): array
    {
        // Without the fields required on create that these tests don't need
        $fields = array_filter(parent::fields(), fn (Field $field): bool => ! in_array($field->name(), ['status', 'preview_password'], true));

        return array_values(array_map(fn (Field $field): Field => match ($field->name()) {
            'title' => RecordingText::make('title')->label('Title')->required()->max(120),
            'featured' => Boolean::make('featured')->canUpdate(fn (): bool => false),
            default => $field,
        }, $fields));
    }
}

/**
 * A text field that records the agent of the saves it prepares.
 */
class RecordingText extends Text
{
    /**
     * @var list<AgentCaller|null>
     */
    public static array $agents = [];

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        self::$agents[] = $context->agent;

        return $value;
    }
}
