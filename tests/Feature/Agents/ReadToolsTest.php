<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Panneau\Agents\Tool;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\Describe;
use Panneau\Agents\Tools\FindItems;
use Panneau\Agents\Tools\GetItem;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Policies\PostPolicy;

/**
 * The read tools of agents, called as their user.
 */
class ReadToolsTest extends HttpTestCase
{
    public function test_describe_lists_the_resources_the_user_can_list(): void
    {
        $this->assertContains('posts', array_column($this->data(Describe::class, [])['resources'], 'id'));

        PostPolicy::$denied = ['viewAny'];
        $this->assertNotContains('posts', array_column($this->data(Describe::class, [])['resources'], 'id'));
    }

    public function test_describe_a_resource_with_its_parts(): void
    {
        $all = $this->data(Describe::class, ['resource' => 'posts']);
        $this->assertSame('posts', $all['id']);
        $this->assertArrayHasKey('fields', $all);
        $this->assertArrayHasKey('actions', $all);

        $list = $this->data(Describe::class, ['resource' => 'posts', 'parts' => ['list']]);
        $this->assertArrayHasKey('list', $list);
        $this->assertArrayNotHasKey('fields', $list);
        $this->assertArrayNotHasKey('actions', $list);
    }

    public function test_describe_a_resource_the_user_can_not_see_is_not_found(): void
    {
        $this->assertSame(404, $this->callTool(Describe::class, ['resource' => 'unknown'])->status());

        PostPolicy::$denied = ['viewAny'];
        $result = $this->callTool(Describe::class, ['resource' => 'posts']);
        $this->assertTrue($result->isError());
        $this->assertSame(404, $result->status());
    }

    public function test_describe_refuses_parts_without_a_resource_and_unknown_parts(): void
    {
        $this->assertErrors(['parts'], $this->callTool(Describe::class, ['parts' => ['fields']]));
        $this->assertErrors(['parts.0'], $this->callTool(Describe::class, ['resource' => 'posts', 'parts' => ['columns']]));
        $this->assertErrors(['other'], $this->callTool(Describe::class, ['other' => true]));
    }

    public function test_describe_translates_the_labels_of_the_built_in_actions(): void
    {
        $this->app->setLocale('fr');
        $this->app['translator']->addLines(['*.Delete' => 'Supprimer'], 'fr');

        $actions = array_column($this->data(Describe::class, ['resource' => 'posts', 'parts' => ['actions']])['actions'], null, 'id');

        $this->assertSame('Supprimer', $actions['delete']['label']);
    }

    public function test_find_items_searches_without_a_resource(): void
    {
        $this->createPost(['title' => 'A summer']);
        $this->createPost(['title' => 'Winter']);

        $data = $this->data(FindItems::class, ['q' => 'summer', 'limit' => 5])['data'];

        $this->assertSame('posts', $data[0]['resource']);
        $this->assertSame(['A summer'], array_column($data[0]['items'], 'title'));
        // The fields of the list's columns, without the abilities
        $this->assertSame(['id', 'version', 'title', 'status', 'published_at'], array_keys($data[0]['items'][0]));
    }

    public function test_find_items_lists_the_columns_of_a_resource_without_their_abilities(): void
    {
        $this->createPost(['title' => 'Hello']);

        $item = $this->data(FindItems::class, ['resource' => 'posts'])['data'][0];

        $this->assertSame(['id', 'version', 'title', 'status', 'published_at'], array_keys($item));
    }

    public function test_find_items_lists_a_resource(): void
    {
        $this->createPost(['title' => 'B summer', 'status' => 'published']);
        $this->createPost(['title' => 'A summer', 'status' => 'published']);
        $this->createPost(['title' => 'C summer', 'status' => 'draft']);

        $result = $this->data(FindItems::class, [
            'resource' => 'posts',
            'filter' => ['status' => ['published']],
            'sort' => 'title',
            'fields' => ['title'],
            'perPage' => 50,
        ]);

        $this->assertSame(['A summer', 'B summer'], array_column($result['data'], 'title'));
        $this->assertSame(['id', 'version', 'title'], array_keys($result['data'][0]));
        $this->assertSame(['page' => 1, 'perPage' => 50, 'total' => 2, 'lastPage' => 1], $result['meta']);
    }

    public function test_find_items_refuses_the_arguments_of_the_other_mode(): void
    {
        $this->assertErrors(['q'], $this->callTool(FindItems::class, []));
        foreach (['filter' => ['status' => 'draft'], 'sort' => 'title', 'page' => 2, 'cursor' => 'abc', 'perPage' => 10, 'fields' => ['title']] as $name => $value) {
            $this->assertErrors([$name], $this->callTool(FindItems::class, ['q' => 'summer', $name => $value]));
        }
        $this->assertErrors(['limit'], $this->callTool(FindItems::class, ['resource' => 'posts', 'limit' => 5]));
        $this->assertErrors(['cursor'], $this->callTool(FindItems::class, ['resource' => 'posts', 'page' => 1, 'cursor' => 'abc']));
    }

    public function test_find_items_takes_at_most_50_items_a_page(): void
    {
        $this->assertErrors(['perPage'], $this->callTool(FindItems::class, ['resource' => 'posts', 'perPage' => 51]));
    }

    public function test_find_items_in_a_resource_the_user_can_not_list_is_forbidden(): void
    {
        PostPolicy::$denied = ['viewAny'];

        $this->assertSame(403, $this->callTool(FindItems::class, ['resource' => 'posts'])->status());
    }

    public function test_get_item_with_its_version(): void
    {
        $post = $this->createPost(['title' => 'Hello']);

        $item = $this->data(GetItem::class, ['resource' => 'posts', 'id' => (string) $post->getKey()]);
        $this->assertSame('Hello', $item['title']);
        $this->assertIsString($item['version']);
        $this->assertGreaterThan(4, count($item));

        $item = $this->data(GetItem::class, ['resource' => 'posts', 'id' => (string) $post->getKey(), 'fields' => ['title']]);
        $this->assertSame(['id', 'version', 'abilities', 'title'], array_keys($item));
    }

    public function test_get_item_the_user_can_not_view(): void
    {
        $post = $this->createPost();
        $this->assertSame(404, $this->callTool(GetItem::class, ['resource' => 'posts', 'id' => (string) $this->createPost(['team_id' => 2])->getKey()])->status());
        $this->assertErrors(['id'], $this->callTool(GetItem::class, ['resource' => 'posts']));

        PostPolicy::$denied = ['view'];
        $result = $this->callTool(GetItem::class, ['resource' => 'posts', 'id' => (string) $post->getKey()]);

        $this->assertSame(403, $result->status());
        $this->assertSame(['type' => 'about:blank', 'title' => 'Forbidden', 'status' => 403], $result->problem);
    }

    public function test_tools_describe_themselves(): void
    {
        foreach ([Describe::class => 'describe', FindItems::class => 'find_items', GetItem::class => 'get_item'] as $class => $name) {
            $tool = $this->app->make($class);
            $this->assertInstanceOf(Tool::class, $tool);
            $this->assertSame($name, $tool->name());
            $this->assertTrue($tool->readOnly());
            $this->assertSame('object', $tool->inputSchema()['type']);
            $this->assertFalse($tool->inputSchema()['additionalProperties']);
        }
    }

    /**
     * @param  class-string<Tool>  $tool
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $tool, array $arguments): ToolResult
    {
        $user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): GenericUser => $user);

        return $this->app->make($tool)->handle(new ToolCall($user, $arguments, $request));
    }

    /**
     * @param  class-string<Tool>  $tool
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function data(string $tool, array $arguments): array
    {
        $result = $this->callTool($tool, $arguments);
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
