<?php

namespace Panneau\Tests\Unit\Navigation;

use InvalidArgumentException;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
use PHPUnit\Framework\TestCase;
use TestApp\Panneau\Resources\PostsResource;

class NavigationBuildersTest extends TestCase
{
    public function test_a_page(): void
    {
        $page = Page::make('post-stats', 'post-stats')
            ->label('Stats')
            ->path('/stats')
            ->props(['period' => 'month', 'filters' => []])
            ->params(['id', 'tab_2']);

        $this->assertSame('post-stats', $page->id());
        $this->assertSame([
            'component' => 'post-stats',
            'path' => '/stats',
            'label' => 'Stats',
            'props' => ['period' => 'month', 'filters' => []],
            'params' => ['id', 'tab_2'],
        ], $page->toDefinition());
    }

    public function test_a_page_leaves_out_what_it_does_not_set(): void
    {
        // Empty props would be a JSON list where the protocol wants an object
        $this->assertSame(['component' => 'reports'], Page::make('reports', 'reports')->props([])->params([])->toDefinition());
    }

    public function test_a_page_needs_an_id_and_a_component(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The page "reports" needs a component.');

        Page::make('reports', '');
    }

    public function test_a_page_needs_an_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A page needs an id.');

        Page::make('', 'reports');
    }

    public function test_params_are_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The page "post-stats" has the param "post-id": params are names such as "id".');

        Page::make('post-stats', 'post-stats')->params(['post-id']);
    }

    public function test_the_built_in_menu_items(): void
    {
        $this->assertSame(['type' => 'resources'], MenuItem::resources()->toDefinition());
        $this->assertSame(
            ['type' => 'resource', 'resource' => PostsResource::class, 'label' => 'Posts', 'icon' => 'file-text'],
            MenuItem::resource(PostsResource::class)->label('Posts')->icon('file-text')->toDefinition(),
        );
        $this->assertSame(['type' => 'resource', 'resource' => 'tags'], MenuItem::resource('tags')->toDefinition());
        $this->assertSame(['type' => 'page', 'page' => 'reports'], MenuItem::page('reports')->toDefinition());
        $this->assertSame(
            ['type' => 'link', 'url' => 'https://example.com', 'label' => 'Site', 'external' => true],
            MenuItem::link('Site', 'https://example.com')->external()->toDefinition(),
        );
        $this->assertSame(
            ['type' => 'link', 'url' => '/help', 'label' => 'Help', 'external' => false],
            MenuItem::link('Help', '/help')->external(false)->toDefinition(),
        );
        $this->assertSame(['type' => 'separator'], MenuItem::separator()->toDefinition());
    }

    public function test_a_group(): void
    {
        $group = MenuItem::group('Content', [
            MenuItem::resource('posts'),
            ['type' => 'page', 'page' => 'reports'],
            MenuItem::group(null, [MenuItem::separator()]),
        ])->icon('folder');

        $this->assertSame('group', $group->type());
        $this->assertSame([
            'type' => 'group',
            'label' => 'Content',
            'icon' => 'folder',
            'children' => [
                ['type' => 'resource', 'resource' => 'posts'],
                ['type' => 'page', 'page' => 'reports'],
                ['type' => 'group', 'children' => [['type' => 'separator']]],
            ],
        ], $group->toDefinition());
    }

    public function test_an_item_of_a_plugin(): void
    {
        $this->assertSame(
            ['type' => 'tenant-switcher', 'tenants' => ['a', 'b'], 'label' => 'Tenant'],
            MenuItem::make('tenant-switcher', ['tenants' => ['a', 'b']])->label('Tenant')->toDefinition(),
        );
        // Its properties don't replace its type
        $this->assertSame(['type' => 'custom'], MenuItem::make('custom', ['type' => 'other'])->toDefinition());
        $this->assertSame(
            ['type' => 'custom', 'children' => [['type' => 'separator']]],
            MenuItem::make('custom')->children([MenuItem::separator()])->toDefinition(),
        );
    }

    public function test_an_item_needs_a_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A menu item needs a type.');

        MenuItem::make('');
    }
}
