<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use LogicException;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * The pages and menus of the app shell, in the definition.
 */
class NavigationTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?Authenticatable $user = null): array
    {
        return $this->app->make(DefinitionBuilder::class)->build($user);
    }

    public function test_no_pages_or_menus_by_default(): void
    {
        $definition = $this->definition();

        $this->assertArrayNotHasKey('pages', $definition);
        $this->assertArrayNotHasKey('menus', $definition);
    }

    public function test_pages_and_menus_from_the_service_provider(): void
    {
        Panneau::pages([
            Page::make('home', 'dashboard')->label('Dashboard'),
            Page::make('reports', 'reports')->label('Reports')->path('/reports')->props(['period' => 'month']),
            Page::make('post-stats', 'post-stats')->params(['id']),
        ]);
        Panneau::menu('main', [
            MenuItem::resources(),
            MenuItem::resource(PostsResource::class)->label('Posts')->icon('file-text'),
            MenuItem::resource('tags'),
            MenuItem::page('reports'),
            MenuItem::link('Site', 'https://example.com')->external(),
            MenuItem::separator(),
            MenuItem::group('Content', [
                MenuItem::resource(AuthorsResource::class),
                MenuItem::group(null, [MenuItem::page('home')]),
            ])->icon('folder'),
            MenuItem::make('tenant-switcher', ['resource' => 'NotAResource']),
        ]);

        $definition = $this->definition();

        $this->assertValidDefinition($definition);
        $this->assertSame([
            'home' => ['component' => 'dashboard', 'label' => 'Dashboard'],
            'reports' => ['component' => 'reports', 'path' => '/reports', 'label' => 'Reports', 'props' => ['period' => 'month']],
            'post-stats' => ['component' => 'post-stats', 'params' => ['id']],
        ], $definition['pages']);
        $this->assertSame(['main' => [
            ['type' => 'resources'],
            ['type' => 'resource', 'resource' => 'posts', 'label' => 'Posts', 'icon' => 'file-text'],
            ['type' => 'resource', 'resource' => 'tags'],
            ['type' => 'page', 'page' => 'reports'],
            ['type' => 'link', 'url' => 'https://example.com', 'label' => 'Site', 'external' => true],
            ['type' => 'separator'],
            ['type' => 'group', 'label' => 'Content', 'icon' => 'folder', 'children' => [
                ['type' => 'resource', 'resource' => 'authors'],
                ['type' => 'group', 'children' => [['type' => 'page', 'page' => 'home']]],
            ]],
            // The types of plugins are left as they are
            ['type' => 'tenant-switcher', 'resource' => 'NotAResource'],
        ]], $definition['menus']);
        // Right after the resources, as in the protocol
        $keys = array_keys($definition);
        $this->assertSame(['resources', 'pages', 'menus', 'auth'], array_slice($keys, array_search('resources', $keys, true), 4));
    }

    public function test_pages_and_menus_from_the_config(): void
    {
        $this->app['config']->set('panneau.pages', [
            'reports' => ['component' => 'reports', 'label' => 'Reports', 'props' => [], 'path' => null],
            ['id' => 'stats', 'component' => 'stats'],
        ]);
        $this->app['config']->set('panneau.menus', [
            'main' => [
                ['type' => 'resource', 'resource' => PostsResource::class],
                ['type' => 'page', 'page' => 'reports', 'icon' => null],
                ['type' => 'group', 'label' => 'More', 'children' => [['type' => 'page', 'page' => 'stats']]],
            ],
            'footer' => [],
        ]);

        $definition = $this->definition();

        $this->assertValidDefinition($definition);
        // Empty props and null values are left out
        $this->assertSame([
            'reports' => ['component' => 'reports', 'label' => 'Reports'],
            'stats' => ['component' => 'stats'],
        ], $definition['pages']);
        $this->assertSame([
            'main' => [
                ['type' => 'resource', 'resource' => 'posts'],
                ['type' => 'page', 'page' => 'reports'],
                ['type' => 'group', 'label' => 'More', 'children' => [['type' => 'page', 'page' => 'stats']]],
            ],
            // An empty menu is sent: it has no entries, rather than the default ones
            'footer' => [],
        ], $definition['menus']);
        $this->assertSame('{"main":[{"type":"resource","resource":"posts"},{"type":"page","page":"reports"},{"type":"group","label":"More","children":[{"type":"page","page":"stats"}]}],"footer":[]}', json_encode($definition['menus']));
    }

    public function test_the_service_provider_wins_over_the_config(): void
    {
        $this->app['config']->set('panneau.pages', [
            'reports' => ['component' => 'reports', 'label' => 'Reports'],
            'stats' => ['component' => 'stats'],
        ]);
        $this->app['config']->set('panneau.menus', [
            'main' => [['type' => 'resources']],
            'footer' => [['type' => 'page', 'page' => 'stats']],
        ]);
        Panneau::pages([Page::make('reports', 'my-reports')]);
        Panneau::pages(['help' => ['component' => 'help']]);
        Panneau::menu('main', [MenuItem::page('reports')]);

        $definition = $this->definition();

        $this->assertSame([
            'reports' => ['component' => 'my-reports'],
            'stats' => ['component' => 'stats'],
            'help' => ['component' => 'help'],
        ], $definition['pages']);
        $this->assertSame([
            'main' => [['type' => 'page', 'page' => 'reports']],
            'footer' => [['type' => 'page', 'page' => 'stats']],
        ], $definition['menus']);
    }

    public function test_a_later_menu_replaces_an_earlier_one(): void
    {
        Panneau::menu('main', [MenuItem::resources()]);
        Panneau::menu('main', [MenuItem::separator()]);

        $this->assertSame(['main' => [['type' => 'separator']]], $this->definition()['menus']);
    }

    public function test_closures_get_the_user_each_time_the_definition_is_built(): void
    {
        $calls = 0;
        Panneau::pages(function (?Authenticatable $user) use (&$calls): array {
            $calls++;

            return $user?->getAuthIdentifier() === 1 ? [Page::make('reports', 'reports')->label(__('Reports'))] : [];
        });
        Panneau::menu('main', fn (?Authenticatable $user, Config $config): array => [
            MenuItem::resources(),
            ...($user?->getAuthIdentifier() === 1 ? [MenuItem::page('reports')] : []),
            MenuItem::link((string) $config->get('panneau.name', 'Site'), '/'),
        ]);
        $this->app['config']->set('panneau.name', 'Admin');
        $this->app['translator']->addLines(['*.Reports' => 'Rapports'], 'fr');

        $guest = $this->definition();
        $this->assertArrayNotHasKey('pages', $guest);
        $this->assertSame([['type' => 'resources'], ['type' => 'link', 'url' => '/', 'label' => 'Admin']], $guest['menus']['main']);

        $this->app->setLocale('fr');
        $admin = $this->definition(new GenericUser(['id' => 1, 'name' => 'Ada']));
        $this->assertValidDefinition($admin);
        // In the locale of the request
        $this->assertSame(['reports' => ['component' => 'reports', 'label' => 'Rapports']], $admin['pages']);
        $this->assertSame(['type' => 'page', 'page' => 'reports'], $admin['menus']['main'][1]);

        $this->assertSame(2, $calls);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'an unregistered resource class' => [
            ['menus' => ['main' => [MenuItem::resource('App\Panneau\Resources\Missing')]]],
            'The menu "main" links to the resource "App\\\\Panneau\\\\Resources\\\\Missing", which is not registered.',
        ];
        yield 'an unregistered resource id' => [
            ['menus' => ['main' => [MenuItem::group('Content', [MenuItem::resource('postz')])]]],
            'The menu "main" links to the resource "postz", which is not registered.',
        ];
        yield 'a resource item without a resource' => [
            ['menus' => ['main' => [['type' => 'resource']]]],
            'The menu "main" links to the resource null, which is not registered.',
        ];
        yield 'a page that is not declared' => [
            ['pages' => [Page::make('reports', 'reports')], 'menus' => ['main' => [MenuItem::page('report')]]],
            'The menu "main" links to the page "report", which is not declared in panneau.pages or with Panneau::pages().',
        ];
        yield 'a page with params' => [
            ['pages' => [Page::make('post-stats', 'post-stats')->params(['id'])], 'menus' => ['main' => [MenuItem::page('post-stats')]]],
            'The menu "main" links to the page "post-stats", which has params: a page about one thing can\'t be in a menu.',
        ];
        yield 'a link without a URL' => [
            ['menus' => ['main' => [['type' => 'link', 'label' => 'Site']]]],
            'The menu "main" has a link without a URL',
        ];
        yield 'an item without a type' => [
            ['menus' => ['main' => [['label' => 'Site']]]],
            'The menu "main" has an item without a type',
        ];
        yield 'a page without a component' => [
            ['pages' => ['reports' => ['label' => 'Reports']]],
            'The page "reports" has no component.',
        ];
        yield 'a page without an id' => [
            ['pages' => [['component' => 'reports']]],
            'The page {"component":"reports"} has no id: key the pages by id.',
        ];
        yield 'a page with invalid params' => [
            ['pages' => ['post-stats' => ['component' => 'post-stats', 'params' => ['post-id']]]],
            'The page "post-stats" has the params ["post-id"]',
        ];
        yield 'a closure that returns something else' => [
            ['menus' => ['main' => fn (): string => 'main']],
            'The declaration of the menu "main" gives string instead of an array.',
        ];
    }

    /**
     * @param  array{pages?: array<array-key, mixed>, menus?: array<string, mixed>}  $declarations
     */
    #[DataProvider('invalidDeclarations')]
    public function test_invalid_declarations_throw_when_the_definition_is_built(array $declarations, string $message): void
    {
        if (isset($declarations['pages'])) {
            Panneau::pages($declarations['pages']);
        }
        foreach ($declarations['menus'] ?? [] as $name => $items) {
            Panneau::menu($name, $items);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }

    public function test_menus_of_the_config_are_keyed_by_name(): void
    {
        $this->app['config']->set('panneau.menus', [[['type' => 'resources']]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('panneau.menus has a menu without a name (0)');

        $this->definition();
    }
}
