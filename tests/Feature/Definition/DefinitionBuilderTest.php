<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Auth\GenericUser;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Resources\ResourceRegistry;
use Panneau\Support\Facade;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\PostPolicy;

class DefinitionBuilderTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('panneau.locales', ['fr', 'en']);
        $this->app['config']->set('panneau.name', 'Test admin');
        $this->app->make(ResourceRegistry::class)->register([
            PostsResource::class,
            AuthorsResource::class,
            TagsResource::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return $this->app->make(DefinitionBuilder::class)->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(string $id): array
    {
        foreach ($this->definition()['resources'] as $resource) {
            if ($resource['id'] === $id) {
                return $resource;
            }
        }
        $this->fail('No resource '.$id);
    }

    public function test_the_definition_matches_the_protocol(): void
    {
        $this->assertValidDefinition($this->definition());
    }

    public function test_an_empty_definition_matches_the_protocol(): void
    {
        $this->app->forgetInstance(ResourceRegistry::class);

        $definition = $this->definition();

        $this->assertSame([], $definition['resources']);
        $this->assertValidDefinition($definition);
    }

    public function test_the_top_level_properties(): void
    {
        $definition = $this->definition();

        $this->assertSame(1, $definition['protocol']);
        $this->assertSame('Test admin', $definition['name']);
        $this->assertSame('en', $definition['locale']);
        $this->assertSame(['fr', 'en'], $definition['locales']);
        $this->assertSame(['baseUrl' => '/panneau/api'], $definition['api']);
    }

    public function test_the_global_search(): void
    {
        $this->assertSame(['enabled' => true], $this->definition()['search']);
        $this->assertSame(
            ['posts' => true, 'authors' => false, 'tags' => false],
            array_column(array_map(fn (array $resource): array => ['id' => $resource['id'], 'search' => $resource['features']['search']], $this->definition()['resources']), 'search', 'id'),
        );

        $this->app['config']->set('panneau.search.enabled', false);
        $definition = $this->definition();
        $this->assertSame(['enabled' => false], $definition['search']);
        $this->assertValidDefinition($definition);
    }

    public function test_locales_default_to_the_app_locale(): void
    {
        $this->app['config']->set('panneau.locales', null);
        $this->app->setLocale('fr');

        $this->assertSame(['fr'], $this->definition()['locales']);
    }

    public function test_a_resource(): void
    {
        $posts = $this->resource('posts');

        $this->assertSame(['singular' => 'post', 'plural' => 'posts'], $posts['label']);
        $this->assertSame('title', $posts['itemTitle']);
        $this->assertSame(
            ['title', 'slug', 'subtitle', 'body', 'status', 'featured', 'published_at', 'author', 'tags', 'seo', 'details', 'blocks'],
            array_column($posts['fields'], 'name'),
        );
        $this->assertSame(['search' => true], $posts['features']);
    }

    public function test_fields(): void
    {
        $fields = array_column($this->resource('posts')['fields'], null, 'name');

        $this->assertSame([
            'name' => 'title',
            'type' => 'text',
            'label' => 'Title',
            'rules' => [['rule' => 'required'], ['rule' => 'max', 'value' => 120]],
        ], $fields['title']);
        $this->assertTrue($fields['subtitle']['localized']);
        $this->assertSame([
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published', 'tone' => 'success'],
            ['value' => 'archived', 'label' => 'Archived', 'tone' => 'warning'],
        ], $fields['status']['options']);
        $this->assertSame('draft', $fields['status']['default']);
        $this->assertSame(['name' => 'author', 'type' => 'relation', 'resource' => 'authors'], $fields['author']);
        $this->assertSame(['name' => 'tags', 'type' => 'relation', 'resource' => 'tags', 'multiple' => true], $fields['tags']);
        $this->assertSame(['title', 'description'], array_column($fields['seo']['fields'], 'name'));
        $this->assertSame(['quote', 'image'], array_column($fields['blocks']['types'], 'id'));
        $this->assertSame(
            ['name' => 'image', 'type' => 'media', 'rules' => [['rule' => 'accept', 'value' => ['image/*']], ['rule' => 'required']], 'accept' => ['image/*']],
            $fields['blocks']['types'][1]['fields'][0],
        );
    }

    public function test_the_form_layout(): void
    {
        $layout = $this->resource('posts')['form']['layout'];

        $this->assertSame('tabs', $layout[0]['type']);
        $this->assertSame(
            ['type' => 'row', 'children' => [
                ['type' => 'field', 'name' => 'title', 'width' => 8],
                ['type' => 'field', 'name' => 'status', 'width' => 4],
            ]],
            $layout[0]['children'][0]['children'][0],
        );
        $this->assertSame(
            ['type' => 'section', 'label' => 'SEO', 'collapsible' => true, 'children' => [['type' => 'field', 'name' => 'seo']]],
            $layout[0]['children'][1]['children'][0],
        );
        $this->assertSame('sidebar', $layout[1]['type']);
    }

    public function test_a_resource_without_layout_has_no_form(): void
    {
        $this->assertArrayNotHasKey('form', $this->resource('authors'));
    }

    public function test_the_list(): void
    {
        $list = $this->resource('posts')['list'];

        $this->assertSame([
            ['field' => 'title', 'sortable' => true],
            ['field' => 'status'],
            ['field' => 'published_at', 'sortable' => true],
        ], $list['columns']);
        $this->assertSame(['status', 'featured', 'author_id', 'published_at'], array_column($list['filters'], 'id'));
        $this->assertSame('authors', $list['filters'][2]['resource']);
        $this->assertSame(['id' => 'published_at', 'type' => 'date-range'], $list['filters'][3]);
        $this->assertSame(['title', 'published_at'], $list['sorts']);
        $this->assertSame('-published_at', $list['defaultSort']);
        $this->assertTrue($list['search']);
    }

    public function test_default_columns(): void
    {
        $this->assertSame([['field' => 'name']], $this->resource('authors')['list']['columns']);
        $this->assertSame([['field' => 'name'], ['field' => 'slug']], $this->resource('tags')['list']['columns']);
        $this->assertFalse($this->resource('authors')['list']['search']);
    }

    public function test_actions(): void
    {
        $actions = array_column($this->resource('posts')['actions'], null, 'id');

        $this->assertSame([
            'id' => 'publish-posts',
            'label' => 'Publish posts',
            'icon' => 'send',
            'kind' => 'server',
            'scope' => ['item', 'selection'],
            'placement' => ['row', 'toolbar'],
            'confirm' => ['message' => 'Publish {count, plural, one {# post} other {# posts}}?', 'confirmLabel' => 'Publish'],
            'fields' => [
                ['name' => 'notify', 'type' => 'boolean', 'label' => 'Notify subscribers'],
                ['name' => 'note', 'type' => 'text', 'rules' => [['rule' => 'max', 'value' => 10]]],
            ],
            'abilities' => ['run' => false],
        ], $actions['publish-posts']);
        $this->assertSame([
            'id' => 'preview',
            'label' => 'Preview',
            'kind' => 'link',
            'scope' => ['item'],
            'placement' => ['form'],
            'url' => '/posts/{id}/preview',
        ], $actions['preview']);
        $this->assertSame([
            'id' => 'copy-link',
            'label' => 'Copy link',
            'kind' => 'client',
            'scope' => ['item'],
            'component' => 'copy-link',
        ], $actions['copy-link']);
    }

    public function test_without_policies_everything_is_denied(): void
    {
        $this->assertSame(['viewAny' => false, 'create' => false], $this->resource('tags')['abilities']);
    }

    public function test_allow_without_policies(): void
    {
        Facade::allowWithoutPolicies();

        $this->assertSame(['viewAny' => true, 'create' => true], $this->resource('tags')['abilities']);
    }

    public function test_policies_decide_for_the_user(): void
    {
        $user = new GenericUser(['id' => 1]);
        PostPolicy::$denied = ['create'];

        try {
            $posts = $this->app->make(DefinitionBuilder::class)->resource($this->app->make(ResourceRegistry::class)->find('posts'), $user);
        } finally {
            PostPolicy::$denied = [];
        }

        $this->assertSame(['viewAny' => true, 'create' => false], $posts['abilities']);
        $this->assertSame(['run' => true], array_column($posts['actions'], null, 'id')['publish-posts']['abilities']);
    }

    public function test_auth_and_user(): void
    {
        $definition = $this->app->make(DefinitionBuilder::class)->build(new GenericUser(['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.com']));

        $this->assertSame(['mode' => 'external', 'loginUrl' => '/login', 'logoutUrl' => null], $definition['auth']);
        $this->assertSame(['id' => '7', 'name' => 'Ada', 'email' => 'ada@example.com', 'abilities' => ['panneau.view' => true]], $definition['user']);
        $this->assertValidDefinition($definition);
    }

    public function test_the_logout_url_of_the_external_mode(): void
    {
        $this->app['config']->set('panneau.auth.logout_url', '/logout');

        $definition = $this->definition();

        $this->assertSame(['mode' => 'external', 'loginUrl' => '/login', 'logoutUrl' => '/logout'], $definition['auth']);
        $this->assertValidDefinition($definition);
    }

    public function test_the_panneau_mode_has_no_login_or_logout_url(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $this->app['config']->set('panneau.auth.logout_url', '/logout');

        $definition = $this->definition();

        $this->assertSame(['mode' => 'panneau', 'loginUrl' => null, 'logoutUrl' => null], $definition['auth']);
        $this->assertValidDefinition($definition);
    }

    public function test_no_theme_by_default(): void
    {
        $this->assertArrayNotHasKey('theme', $this->definition());
    }

    public function test_the_theme(): void
    {
        $theme = [
            'extends' => ['brand', 'brand-print'],
            'colorScheme' => 'auto',
            'density' => 'compact',
            'label' => 'Brand',
            'tokens' => [
                'panneau-color-accent' => 'var(--panneau-palette-blue)',
                'panneau-font-family-body' => '"Geist", system-ui, sans-serif',
            ],
            'dark' => ['panneau-color-surface' => '#101014'],
            'palette' => ['blue' => '#0055ff', 'sand' => 'oklch(0.9 0.04 85)'],
            'tones' => [
                'brand' => '#0055ff',
                'archived' => [
                    'text' => '#555', 'subtle' => '#eee', 'border' => '#ccc', 'outline' => '#999',
                    'solid' => '#666', 'solidHover' => '#555', 'solidText' => '#fff',
                    'dark' => ['text' => '#bbb'],
                ],
            ],
            'components' => ['button' => ['variants' => ['pill' => ['panneau-button-radius' => '999px']]]],
            'densities' => ['compact' => ['panneau-control-height' => '1.75rem']],
            'fonts' => [
                ['family' => 'Geist', 'src' => "url(/fonts/geist.woff2) format('woff2')", 'weight' => '100 900', 'display' => 'swap'],
                ['stylesheet' => 'https://fonts.example.com/css?family=Geist+Mono'],
            ],
        ];
        $this->app['config']->set('panneau.theme', $theme);

        $definition = $this->definition();

        $this->assertSame($theme, $definition['theme']);
        $this->assertValidDefinition($definition);
    }

    public function test_a_theme_that_only_extends_a_plugin_theme(): void
    {
        $this->app['config']->set('panneau.theme', ['extends' => 'brand']);

        $definition = $this->definition();

        $this->assertSame(['extends' => 'brand'], $definition['theme']);
        $this->assertValidDefinition($definition);
    }

    public function test_null_values_and_empty_maps_are_left_out_of_the_theme(): void
    {
        $this->app['config']->set('panneau.theme', [
            'colorScheme' => 'dark',
            'density' => null,
            'tokens' => [],
            'components' => ['button' => ['variants' => []]],
            'fonts' => [],
        ]);

        $definition = $this->definition();

        $this->assertSame(['colorScheme' => 'dark'], $definition['theme']);
        $this->assertValidDefinition($definition);
    }

    public function test_an_empty_theme_is_left_out(): void
    {
        $this->app['config']->set('panneau.theme', ['tokens' => [], 'extends' => null]);

        $this->assertArrayNotHasKey('theme', $this->definition());
    }
}
