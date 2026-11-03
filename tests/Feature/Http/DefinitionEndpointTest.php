<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Panneau\Resources\PostsResource;

class DefinitionEndpointTest extends HttpTestCase
{
    use ValidatesProtocol;

    public function test_the_definition(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response
            ->assertJsonPath('user', ['id' => '1', 'name' => 'Ada', 'email' => 'ada@example.com', 'abilities' => ['panneau.view' => true]])
            ->assertJsonPath('resources.0.id', 'posts')
            ->assertJsonPath('resources.0.abilities', ['viewAny' => true, 'create' => true])
            ->assertJsonPath('resources.2.abilities', ['viewAny' => false, 'create' => false]);
    }

    public function test_it_is_translated_in_the_current_locale(): void
    {
        $this->app->setLocale('fr');

        $this->getJson(self::API.'/definition')->assertJsonPath('locale', 'fr');
    }

    public function test_the_theme(): void
    {
        $this->app['config']->set('panneau.theme', [
            'extends' => 'brand',
            'colorScheme' => 'auto',
            'tokens' => ['panneau-color-accent' => '#0055ff'],
            'tones' => ['brand' => '#0055ff'],
        ]);

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response->assertJsonPath('theme', [
            'extends' => 'brand',
            'colorScheme' => 'auto',
            'tokens' => ['panneau-color-accent' => '#0055ff'],
            'tones' => ['brand' => '#0055ff'],
        ]);
    }

    public function test_no_theme_by_default(): void
    {
        $this->getJson(self::API.'/definition')->assertOk()->assertJsonMissingPath('theme');
    }

    public function test_pages_and_menus_from_the_config(): void
    {
        $this->app['config']->set('panneau.pages', [
            'home' => ['component' => 'dashboard', 'props' => []],
            'reports' => ['component' => 'reports', 'label' => 'Reports'],
        ]);
        $this->app['config']->set('panneau.menus', ['main' => [
            ['type' => 'resource', 'resource' => PostsResource::class, 'icon' => 'file-text'],
            ['type' => 'separator'],
            ['type' => 'page', 'page' => 'reports'],
        ]]);

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response
            ->assertJsonPath('pages', ['home' => ['component' => 'dashboard'], 'reports' => ['component' => 'reports', 'label' => 'Reports']])
            ->assertJsonPath('menus.main', [
                ['type' => 'resource', 'resource' => 'posts', 'icon' => 'file-text'],
                ['type' => 'separator'],
                ['type' => 'page', 'page' => 'reports'],
            ]);
        // A map, not a list, even with a page whose props are empty
        $this->assertStringContainsString('"pages":{"home":{"component":"dashboard"}', (string) $response->getContent());
    }

    public function test_pages_and_menus_for_the_user_in_the_locale_of_the_request(): void
    {
        $this->app['translator']->addLines(['*.Reports' => 'Rapports', '*.Content' => 'Contenu'], 'fr');
        Panneau::pages(fn (?Authenticatable $user): array => [
            Page::make('reports', 'reports')->label(__('Reports')),
            ...($user?->getAuthIdentifier() === 1 ? [Page::make('post-stats', 'post-stats')->params(['id'])] : []),
        ]);
        Panneau::menu('main', fn (?Authenticatable $user): array => [
            MenuItem::group(__('Content'), [MenuItem::resources()])->icon('folder'),
            MenuItem::page('reports'),
            MenuItem::link($user?->getAuthIdentifier() === 1 ? 'Ada' : 'Someone', '/')->external(),
        ]);
        $this->app->setLocale('fr');

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response
            ->assertJsonPath('pages', [
                'reports' => ['component' => 'reports', 'label' => 'Rapports'],
                'post-stats' => ['component' => 'post-stats', 'params' => ['id']],
            ])
            ->assertJsonPath('menus.main', [
                ['type' => 'group', 'label' => 'Contenu', 'icon' => 'folder', 'children' => [['type' => 'resources']]],
                ['type' => 'page', 'page' => 'reports'],
                ['type' => 'link', 'url' => '/', 'label' => 'Ada', 'external' => true],
            ]);
    }

    public function test_a_menu_that_links_to_a_missing_page_fails(): void
    {
        Panneau::menu('main', [MenuItem::page('reports')]);

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The menu "main" links to the page "reports", which is not declared');

        $this->getJson(self::API.'/definition');
    }

    public function test_no_pages_or_menus_by_default(): void
    {
        $this->getJson(self::API.'/definition')->assertOk()->assertJsonMissingPath('pages')->assertJsonMissingPath('menus');
    }

    public function test_no_uploads_by_default(): void
    {
        $this->getJson(self::API.'/definition')->assertOk()->assertJsonMissingPath('uploads');
        $this->postJson(self::API.'/uploads/sign', ['method' => 'POST', 'key' => 'video.mp4'])->assertNotFound();
    }
}
