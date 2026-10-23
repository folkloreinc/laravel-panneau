<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Http\Request;
use LogicException;
use Panneau\Contracts\Repositories\ChecksSlugs;
use Panneau\Fields\Group;
use Panneau\Fields\Slug;
use Panneau\Fields\Text;
use Panneau\Http\Problem;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Document;
use TestApp\Models\Page;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\PagesResource;
use TestApp\Policies\PostPolicy;
use TestApp\Repositories\Adapters\ArrayRepository;

class FieldCheckTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages have no policy
        Panneau::allowWithoutPolicies();
        Panneau::resources([PagesResource::class]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function check(string $resource, string $field, array $body): array
    {
        $response = $this->postJson(self::API.'/resources/'.$resource.'/fields/'.$field.'/check', $body)->assertOk();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPage(array $attributes = []): Page
    {
        return Page::query()->create($attributes + ['team_id' => 1, 'title' => ['fr' => 'Page', 'en' => 'Page']]);
    }

    public function test_the_definition_says_which_fields_check(): void
    {
        $resources = collect($this->getJson(self::API.'/definition')->assertOk()->json('resources'));
        $posts = collect($resources->firstWhere('id', 'posts')['fields']);
        $pages = collect($resources->firstWhere('id', 'pages')['fields']);

        $this->assertSame(
            ['name' => 'slug', 'type' => 'slug', 'localized' => true, 'from' => 'title', 'check' => true],
            $posts->firstWhere('name', 'slug'),
        );
        $this->assertTrue($pages->firstWhere('name', 'slug')['check']);
        $this->assertArrayNotHasKey('check', $posts->firstWhere('name', 'title'));
        // Tags have a slug that doesn't check
        $this->assertArrayNotHasKey('check', collect($resources->firstWhere('id', 'tags')['fields'])->firstWhere('name', 'slug'));
    }

    public function test_only_fields_at_the_top_of_a_resource_check(): void
    {
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'nested';
            }

            public function model(): string
            {
                return Post::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), Group::make('seo')->fields([Slug::make('slug')->unique()])];
            }
        }]);

        $nested = collect($this->getJson(self::API.'/definition')->json('resources'))->firstWhere('id', 'nested');

        $this->assertArrayNotHasKey('check', $nested['fields'][1]['fields'][0]);
        $this->postJson(self::API.'/resources/nested/fields/seo/check', ['value' => 'a'])->assertNotFound();
        $this->postJson(self::API.'/resources/nested/fields/slug/check', ['value' => 'a'])->assertNotFound();
    }

    public function test_an_available_slug(): void
    {
        $this->createPost(['slug' => ['fr' => 'ete', 'en' => 'summer']]);

        $this->assertSame([
            'available' => true,
            'suggestion' => null,
            'prefix' => 'https://example.com/fr/posts/',
            'url' => 'https://example.com/fr/posts/hiver',
            'disabled' => false,
        ], $this->check('posts', 'slug', ['value' => 'hiver', 'locale' => 'fr', 'id' => null, 'values' => []]));
    }

    public function test_a_taken_slug_gets_a_suggestion(): void
    {
        $this->createPost(['slug' => ['fr' => 'ete', 'en' => 'summer']]);
        $this->createPost(['slug' => ['fr' => 'ete-2', 'en' => 'summer-2']]);

        $answer = $this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'fr']);

        $this->assertFalse($answer['available']);
        $this->assertSame('ete-3', $answer['suggestion']);
        $this->assertSame('https://example.com/fr/posts/ete', $answer['url']);
    }

    public function test_slugs_are_checked_per_locale(): void
    {
        $this->createPost(['slug' => ['fr' => 'ete', 'en' => 'summer']]);

        $this->assertTrue($this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'en'])['available']);
        $this->assertFalse($this->check('posts', 'slug', ['value' => 'summer', 'locale' => 'en'])['available']);
        $this->assertTrue($this->check('posts', 'slug', ['value' => 'summer', 'locale' => 'fr'])['available']);
        // The default locale, without one
        $this->assertFalse($this->check('posts', 'slug', ['value' => 'ete'])['available']);
    }

    public function test_the_item_being_edited_does_not_count(): void
    {
        $post = $this->createPost(['slug' => ['fr' => 'ete']]);

        $this->assertTrue($this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'fr', 'id' => (string) $post->id])['available']);
        $this->assertTrue($this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'fr', 'id' => $post->id])['available']);
        $this->assertFalse($this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'fr', 'id' => null])['available']);
    }

    public function test_items_outside_the_scope_do_not_count(): void
    {
        $this->createPost(['team_id' => 2, 'slug' => ['fr' => 'ete']]);

        $this->assertTrue($this->check('posts', 'slug', ['value' => 'ete', 'locale' => 'fr'])['available']);
    }

    public function test_deleted_items_count_and_public_ids_are_route_keys(): void
    {
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'documents';
            }

            public function model(): string
            {
                return Document::class;
            }

            public function fields(): array
            {
                return [Slug::make('name')->unique()];
            }
        }]);
        $kept = Document::query()->create(['name' => 'kept']);
        Document::query()->create(['name' => 'deleted'])->delete();

        $this->assertFalse($this->check('documents', 'name', ['value' => 'deleted'])['available']);
        $this->assertFalse($this->check('documents', 'name', ['value' => 'kept'])['available']);
        $this->assertTrue($this->check('documents', 'name', ['value' => 'kept', 'id' => $kept->uid])['available']);
    }

    public function test_the_closure_of_unique_narrows_the_lookup(): void
    {
        $news = $this->createPage(['slug' => ['fr' => 'nouvelles', 'en' => 'news']]);
        $events = $this->createPage(['slug' => ['fr' => 'evenements', 'en' => 'events']]);
        $this->createPage(['parent_id' => $news->id, 'slug' => ['fr' => 'ete', 'en' => 'summer']]);

        $inNews = $this->check('pages', 'slug', ['value' => 'ete', 'locale' => 'fr', 'values' => ['parent' => (string) $news->id]]);
        $this->assertFalse($inNews['available']);
        $this->assertSame('ete-2', $inNews['suggestion']);

        $this->assertTrue($this->check('pages', 'slug', ['value' => 'ete', 'locale' => 'fr', 'values' => ['parent' => (string) $events->id]])['available']);
        $this->assertTrue($this->check('pages', 'slug', ['value' => 'ete', 'locale' => 'fr', 'values' => ['parent' => null]])['available']);
        // A relation sent as a reference
        $this->assertFalse($this->check('pages', 'slug', ['value' => 'ete', 'locale' => 'fr', 'values' => ['parent' => ['id' => (string) $news->id]]])['available']);
    }

    public function test_the_url_and_the_prefix_follow_the_values(): void
    {
        $news = $this->createPage(['slug' => ['fr' => 'nouvelles', 'en' => 'news']]);

        $answer = $this->check('pages', 'slug', ['value' => 'ete', 'locale' => 'fr', 'values' => ['parent' => (string) $news->id]]);
        $this->assertSame('https://example.com/fr/nouvelles/', $answer['prefix']);
        $this->assertSame('https://example.com/fr/nouvelles/ete', $answer['url']);

        $answer = $this->check('pages', 'slug', ['value' => 'summer', 'locale' => 'en', 'values' => []]);
        $this->assertSame('https://example.com/en/', $answer['prefix']);
        $this->assertSame('https://example.com/en/summer', $answer['url']);
    }

    public function test_the_values_of_the_item_being_edited_fill_in_those_not_sent(): void
    {
        $news = $this->createPage(['slug' => ['fr' => 'nouvelles']]);
        $page = $this->createPage(['parent_id' => $news->id, 'slug' => ['fr' => 'ete']]);
        $this->createPage(['parent_id' => $news->id, 'slug' => ['fr' => 'hiver']]);

        $answer = $this->check('pages', 'slug', ['value' => 'hiver', 'locale' => 'fr', 'id' => (string) $page->id]);

        $this->assertFalse($answer['available']);
        $this->assertSame('https://example.com/fr/nouvelles/hiver', $answer['url']);
    }

    public function test_a_field_that_does_not_apply_to_an_item_is_disabled(): void
    {
        $home = $this->createPage(['handle' => 'home']);

        $this->assertSame(
            ['available' => true, 'suggestion' => null, 'prefix' => null, 'url' => null, 'disabled' => true],
            $this->check('pages', 'slug', ['value' => '', 'locale' => 'fr', 'id' => (string) $home->id]),
        );
        $this->assertFalse($this->check('pages', 'slug', ['value' => 'a-propos', 'locale' => 'fr'])['disabled']);
    }

    public function test_an_empty_value_is_available_and_has_a_prefix(): void
    {
        $this->createPost(['slug' => ['fr' => '']]);

        $this->assertSame([
            'available' => true,
            'suggestion' => null,
            'prefix' => 'https://example.com/fr/posts/',
            'url' => null,
            'disabled' => false,
        ], $this->check('posts', 'slug', ['value' => '', 'locale' => 'fr']));
    }

    public function test_the_check_needs_the_create_ability(): void
    {
        PostPolicy::$denied = ['create'];

        $this->postJson(self::API.'/resources/posts/fields/slug/check', ['value' => 'ete', 'locale' => 'fr'])
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_the_check_of_an_item_needs_the_update_ability(): void
    {
        $locked = $this->createPost(['title' => 'Locked']);
        $other = $this->createPost(['team_id' => 2]);
        PostPolicy::$denied = ['create'];

        $this->postJson(self::API.'/resources/posts/fields/slug/check', ['value' => 'ete', 'id' => (string) $locked->id])->assertForbidden();
        $this->postJson(self::API.'/resources/posts/fields/slug/check', ['value' => 'ete', 'id' => (string) $other->id])->assertNotFound();
        $this->postJson(self::API.'/resources/posts/fields/slug/check', ['value' => 'ete', 'id' => '999'])->assertNotFound();

        $this->assertTrue($this->check('posts', 'slug', ['value' => 'ete', 'id' => (string) $this->createPost()->id])['available']);
    }

    public function test_an_unknown_field_or_a_field_without_check_is_a_404_problem(): void
    {
        foreach (['unknown', 'title', 'subtitle'] as $field) {
            $this->postJson(self::API.'/resources/posts/fields/'.$field.'/check', ['value' => 'ete'])
                ->assertNotFound()
                ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        }
        $this->postJson(self::API.'/resources/unknown/fields/slug/check', ['value' => 'ete'])->assertNotFound();
        // Tags have a slug that doesn't check
        $this->postJson(self::API.'/resources/tags/fields/slug/check', ['value' => 'ete'])->assertNotFound();
    }

    public function test_invalid_requests_are_a_422_problem(): void
    {
        $response = $this->postJson(self::API.'/resources/posts/fields/slug/check', ['locale' => 'de', 'id' => ['1'], 'values' => 'x'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);

        $this->assertEqualsCanonicalizing(['value', 'locale', 'id', 'values'], array_keys($response->json('errors')));
        $this->postJson(self::API.'/resources/posts/fields/slug/check', ['value' => ['ete']])->assertStatus(422);
    }

    public function test_saving_refuses_a_taken_slug_per_locale(): void
    {
        $this->createPost(['slug' => ['fr' => 'ete', 'en' => 'summer']]);

        $response = $this->postJson(self::API.'/resources/posts', [
            'title' => 'New',
            'status' => 'draft',
            'slug' => ['fr' => 'ete', 'en' => 'ete'],
        ])->assertStatus(422);
        $this->assertSame(['slug.fr'], array_keys($response->json('errors')));

        $this->postJson(self::API.'/resources/posts', [
            'title' => 'New',
            'status' => 'draft',
            'slug' => ['fr' => 'summer', 'en' => 'ete'],
        ])->assertCreated();
    }

    public function test_an_item_keeps_its_own_slug(): void
    {
        $post = $this->createPost(['slug' => ['fr' => 'ete']]);
        $other = $this->createPost(['slug' => ['fr' => 'hiver']]);

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['slug' => ['fr' => 'ete', 'en' => 'summer']])->assertOk();
        $this->patchJson(self::API.'/resources/posts/'.$other->id, ['slug' => ['fr' => 'ete']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['slug.fr']]);
    }

    public function test_saving_scopes_the_slug_with_the_values_of_the_item(): void
    {
        $news = $this->createPage(['slug' => ['fr' => 'nouvelles']]);
        $events = $this->createPage(['slug' => ['fr' => 'evenements']]);
        $this->createPage(['parent_id' => $news->id, 'slug' => ['fr' => 'ete']]);
        $page = $this->createPage(['parent_id' => $events->id, 'slug' => ['fr' => 'hiver']]);

        // Free under its own parent, the one the update doesn't send
        $this->patchJson(self::API.'/resources/pages/'.$page->id, ['slug' => ['fr' => 'ete']])->assertOk();
        // Taken under the parent the update sends
        $this->patchJson(self::API.'/resources/pages/'.$page->id, ['parent' => (string) $news->id, 'slug' => ['fr' => 'ete']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['slug.fr']]);
        $this->postJson(self::API.'/resources/pages', [
            'title' => ['fr' => 'Été', 'en' => 'Summer'],
            'parent' => (string) $news->id,
            'slug' => ['fr' => 'ete'],
        ])->assertStatus(422)->assertJsonPath('errors', ['slug.fr' => ['The slug (fr) has already been taken.']]);
        $this->postJson(self::API.'/resources/pages', [
            'title' => ['fr' => 'Été', 'en' => 'Summer'],
            'slug' => ['fr' => 'ete'],
        ])->assertCreated();
    }

    public function test_a_repository_can_check_slugs_itself(): void
    {
        $repository = new class(['1' => (object) ['id' => '1', 'slug' => 'ete']]) extends ArrayRepository implements ChecksSlugs
        {
            /** @var list<array<int, mixed>> */
            public array $lookups = [];

            public function slugExists(string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values): bool
            {
                $this->lookups[] = [$field, $value, $locale, $exceptId, $scope, $values];
                foreach ($this->items as $id => $item) {
                    if ((string) $id !== $exceptId && ($item->{$field} ?? null) === $value) {
                        return true;
                    }
                }

                return false;
            }
        };
        $this->app->instance(ArrayRepository::class, $repository);
        Panneau::resources([$this->notesResource(Slug::make('slug')->unique())]);

        $answer = $this->check('notes', 'slug', ['value' => 'ete', 'values' => ['title' => 'Été', 'other' => 'x']]);

        $this->assertFalse($answer['available']);
        $this->assertSame('ete-2', $answer['suggestion']);
        $this->assertSame(['slug', 'ete', null, null, ['team' => 1], ['title' => 'Été']], $repository->lookups[0]);
        $this->assertTrue($this->check('notes', 'slug', ['value' => 'ete', 'id' => '1'])['available']);

        $this->postJson(self::API.'/resources/notes', ['title' => 'Copy', 'slug' => 'ete'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['slug']]);
        $this->patchJson(self::API.'/resources/notes/1', ['slug' => 'ete'])->assertOk();
    }

    public function test_a_unique_slug_needs_a_repository_that_can_check_it(): void
    {
        $this->app->instance(ArrayRepository::class, new ArrayRepository);
        Panneau::resources([$this->notesResource(Slug::make('slug')->unique())]);
        $this->withoutExceptionHandling();

        $this->expectException(LogicException::class);
        $this->postJson(self::API.'/resources/notes/fields/slug/check', ['value' => 'ete']);
    }

    public function test_a_slug_with_a_url_only_is_always_available(): void
    {
        $this->app->instance(ArrayRepository::class, new ArrayRepository(['1' => (object) ['id' => '1', 'slug' => 'ete']]));
        Panneau::resources([$this->notesResource(Slug::make('slug')->url(fn (string $slug, ?string $locale, array $values, ?string $id): string => '/notes/'.$id.'/'.$slug))]);

        $this->assertSame(
            ['available' => true, 'suggestion' => null, 'prefix' => '/notes/1/', 'url' => '/notes/1/ete', 'disabled' => false],
            $this->check('notes', 'slug', ['value' => 'ete', 'id' => '1']),
        );
    }

    private function notesResource(Slug $slug): Resource
    {
        return new class($slug) extends Resource
        {
            public function __construct(private readonly Slug $slug) {}

            public function id(): string
            {
                return 'notes';
            }

            public function repository(): string
            {
                return ArrayRepository::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), $this->slug];
            }

            public function scope(Request $request): array
            {
                return ['team' => 1];
            }
        };
    }
}
