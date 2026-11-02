<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Http\Problem;
use Panneau\Resources\ResourceRegistry;
use Panneau\Support\Facade as Panneau;
use RuntimeException;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Tag;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\PostPolicy;

class DuplicateEndpointTest extends HttpTestCase
{
    public function test_an_item_is_duplicated(): void
    {
        $post = $this->createPost(['title' => 'Original', 'status' => 'published']);

        $response = $this->postJson(self::API.'/resources/posts/'.$post->id.'/duplicate')->assertCreated();

        $copy = Post::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame($post->id, $copy->id);
        // The duplicated hook of the resource, and its scope
        $this->assertSame('Original (copy)', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->team_id);
        $this->assertSame('Original', $post->refresh()->title);
        $response->assertJsonPath('data.title', 'Original (copy)');
        $response->assertHeader('ETag', '"'.$response->json('data.version').'"');
    }

    public function test_unique_slugs_get_a_free_value(): void
    {
        $post = $this->createPost(['title' => 'Summer', 'slug' => ['fr' => 'ete', 'en' => 'summer']]);
        $this->createPost(['title' => 'Other', 'slug' => ['fr' => 'ete-copy', 'en' => 'other']]);

        $first = $this->postJson(self::API.'/resources/posts/'.$post->id.'/duplicate')->assertCreated();
        $second = $this->postJson(self::API.'/resources/posts/'.$post->id.'/duplicate')->assertCreated();

        $this->assertSame(['fr' => 'ete-copy-2', 'en' => 'summer-copy'], Post::query()->findOrFail($first->json('data.id'))->slug);
        $this->assertSame(['fr' => 'ete-copy-3', 'en' => 'summer-copy-2'], Post::query()->findOrFail($second->json('data.id'))->slug);
    }

    public function test_another_unique_value_is_a_conflict(): void
    {
        Panneau::allowWithoutPolicies();
        // The slug of tags is unique in the table, without unique() on the field
        $tag = Tag::query()->create(['name' => 'News', 'slug' => 'news']);

        $this->postJson(self::API.'/resources/tags/'.$tag->slug.'/duplicate')
            ->assertStatus(409)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->assertSame(1, Tag::query()->count());
    }

    public function test_the_hooks_run_in_the_transaction_of_the_copy(): void
    {
        Panneau::allowWithoutPolicies();
        $tag = Tag::query()->create(['name' => 'News', 'slug' => 'news']);
        $this->app->forgetInstance(ResourceRegistry::class);
        Panneau::resources([new class extends TagsResource
        {
            public function id(): string
            {
                return 'tags';
            }

            public function duplicating(object $item, object $copy): void
            {
                $copy->setAttribute('slug', 'news-2');
            }

            public function duplicated(object $item, object $copy): void
            {
                throw new RuntimeException('After the copy');
            }
        }]);

        $this->withoutExceptionHandling();
        try {
            $this->postJson(self::API.'/resources/tags/'.$tag->slug.'/duplicate');
            $this->fail('The duplicated hook did not run.');
        } catch (RuntimeException $exception) {
            $this->assertSame('After the copy', $exception->getMessage());
        }
        // The copy is rolled back
        $this->assertSame(['news'], Tag::query()->pluck('slug')->all());
    }

    public function test_the_duplicate_method_of_the_policy_decides(): void
    {
        $ada = Author::query()->create(['name' => 'Ada']);
        $unique = Author::query()->create(['name' => 'Unique']);

        $this->postJson(self::API.'/resources/authors/'.$ada->id.'/duplicate')->assertCreated();
        $this->postJson(self::API.'/resources/authors/'.$unique->id.'/duplicate')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->assertSame(1, Author::query()->where('name', 'Unique')->count());
    }

    public function test_without_a_duplicate_method_the_create_ability_decides(): void
    {
        $post = $this->createPost();
        PostPolicy::$denied = ['create'];

        $this->postJson(self::API.'/resources/posts/'.$post->id.'/duplicate')->assertForbidden();
        $this->assertSame(false, $this->getJson(self::API.'/resources/posts')->json('data.0.abilities.duplicate'));
        $this->assertSame(1, Post::query()->count());
    }

    public function test_an_item_that_cant_be_viewed_is_not_duplicated(): void
    {
        $post = $this->createPost(['title' => 'Hidden']);
        PostPolicy::$denied = ['view', 'update'];

        $this->getJson(self::API.'/resources/posts/'.$post->id)->assertForbidden();
        $this->postJson(self::API.'/resources/posts/'.$post->id.'/duplicate')
            ->assertForbidden()
            ->assertJsonMissing(['title' => 'Hidden']);
        $this->assertSame(1, Post::query()->count());
        $this->assertSame(false, $this->getJson(self::API.'/resources/posts')->json('data.0.abilities.duplicate'));
    }

    public function test_an_item_outside_the_scope_is_not_found(): void
    {
        $other = $this->createPost(['team_id' => 2]);

        $this->postJson(self::API.'/resources/posts/'.$other->id.'/duplicate')
            ->assertNotFound()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->postJson(self::API.'/resources/posts/999/duplicate')->assertNotFound();
    }
}
