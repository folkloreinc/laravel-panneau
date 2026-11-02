<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Http\Problem;
use TestApp\Models\Author;
use TestApp\Models\Post;
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
