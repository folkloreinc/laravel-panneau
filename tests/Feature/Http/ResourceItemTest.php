<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\PostsWithSecretResource;
use TestApp\Policies\PostPolicy;

class ResourceItemTest extends HttpTestCase
{
    public function test_show_sends_the_item_and_its_etag(): void
    {
        $post = $this->createPost(['title' => 'Title', 'subtitle' => ['fr' => 'Sous-titre', 'en' => 'Subtitle']]);

        $response = $this->getJson(self::API.'/resources/posts/'.$post->id)->assertOk();

        $response->assertJsonPath('data.id', (string) $post->id)->assertJsonPath('data.subtitle.fr', 'Sous-titre');
        $response->assertHeader('ETag', '"'.$response->json('data.version').'"');
    }

    public function test_items_outside_the_scope_are_not_found(): void
    {
        $post = $this->createPost(['team_id' => 2]);

        $this->getJson(self::API.'/resources/posts/'.$post->id)->assertNotFound()->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'Mine'])->assertNotFound();
        $this->deleteJson(self::API.'/resources/posts/'.$post->id)->assertNotFound();
        $this->assertSame('A post', $post->refresh()->title);
    }

    public function test_show_needs_the_view_ability(): void
    {
        $post = $this->createPost();
        PostPolicy::$denied = ['view'];

        $this->getJson(self::API.'/resources/posts/'.$post->id)->assertForbidden();
    }

    public function test_create(): void
    {
        $author = Author::query()->create(['name' => 'Ada']);

        $response = $this->postJson(self::API.'/resources/posts', [
            'title' => 'New',
            'subtitle' => ['fr' => 'Nouveau', 'en' => 'New'],
            'status' => 'draft',
            'author' => (string) $author->id,
            'team_id' => 3,
            'secret' => 'injected',
            'id' => 999,
        ])->assertCreated();

        $post = Post::query()->findOrFail($response->json('data.id'));
        $this->assertSame('New', $post->title);
        $this->assertSame(1, $post->team_id);
        $this->assertNull($post->secret);
        $this->assertNotSame(999, $post->id);
        $this->assertSame($author->id, $post->author_id);
        $response->assertJsonPath('data.author', ['id' => (string) $author->id, 'label' => 'Ada']);
        $response->assertHeader('ETag', '"'.$response->json('data.version').'"');
    }

    public function test_create_needs_the_create_ability(): void
    {
        PostPolicy::$denied = ['create'];

        $this->postJson(self::API.'/resources/posts', ['title' => 'New', 'status' => 'draft'])->assertForbidden();
        $this->assertSame(0, Post::query()->count());
    }

    public function test_invalid_values_are_a_422_problem_with_dot_paths(): void
    {
        $response = $this->postJson(self::API.'/resources/posts', [
            'title' => str_repeat('a', 200),
            'subtitle' => ['fr' => ['nested']],
            'status' => 'deleted',
            'author' => '999',
            'blocks' => [['type' => 'quote', 'text' => 'Hi'], ['type' => 'image']],
        ]);

        $response
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::VALIDATION)
            ->assertJsonPath('status', 422);
        $this->assertEqualsCanonicalizing(
            ['title', 'subtitle.fr', 'status', 'author', 'blocks.1.image'],
            array_keys($response->json('errors')),
        );
    }

    public function test_update_is_partial(): void
    {
        $post = $this->createPost(['title' => 'Old', 'body' => 'Body']);

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'New', 'secret' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.body', 'Body');
        $this->assertNull($post->refresh()->secret);
    }

    public function test_a_write_only_secret_is_never_sent_back(): void
    {
        Panneau::resources([PostsWithSecretResource::class]);
        $post = $this->createPost(['title' => 'Old', 'secret' => 'stored']);

        $this->getJson(self::API.'/resources/posts-with-secret/'.$post->id)->assertOk()->assertJsonPath('data.secret', null);
        $this->getJson(self::API.'/resources/posts-with-secret')->assertOk()->assertJsonPath('data.0.secret', null);

        // Left out or empty, the stored value stays
        $this->patchJson(self::API.'/resources/posts-with-secret/'.$post->id, ['title' => 'New'])->assertOk();
        $this->patchJson(self::API.'/resources/posts-with-secret/'.$post->id, ['secret' => ''])->assertOk();
        $this->patchJson(self::API.'/resources/posts-with-secret/'.$post->id, ['secret' => null])->assertOk();
        $this->assertSame('stored', $post->refresh()->secret);

        $this->patchJson(self::API.'/resources/posts-with-secret/'.$post->id, ['secret' => 'changed'])
            ->assertOk()
            ->assertJsonPath('data.secret', null);
        $this->assertSame('changed', $post->refresh()->secret);

        $this->patchJson(self::API.'/resources/posts-with-secret/'.$post->id, ['secret' => ['not', 'text']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['secret']);

        $created = $this->postJson(self::API.'/resources/posts-with-secret', ['title' => 'Created', 'status' => 'draft', 'secret' => 'new'])
            ->assertCreated()
            ->assertJsonPath('data.secret', null);
        $this->assertSame('new', Post::query()->findOrFail($created->json('data.id'))->secret);
    }

    public function test_values_of_the_types_of_decision_0033(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.youtube.com/oembed*' => Http::response(['type' => 'video', 'title' => 'A video', 'html' => '<iframe></iframe>'])]);
        $post = $this->createPost();
        $details = [
            'contact' => '+1 514 555-0123',
            'rating' => 7,
            'reading_time' => 420,
            'metadata' => ['source' => 'import', 'ids' => [1, 2]],
            'keywords' => [' culture', 'montreal', 'culture'],
            // Only the URL is kept from the request
            'video' => ['url' => 'https://www.youtube.com/watch?v=1', 'html' => '<script></script>'],
            'place' => ['lat' => 45.509595, 'lng' => -73.565912, 'address' => 'Place des Arts'],
        ];

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['details' => $details])
            ->assertOk()
            ->assertJsonPath('data.details', [
                'contact' => '+1 514 555-0123',
                'rating' => 7,
                'reading_time' => 420,
                'metadata' => ['source' => 'import', 'ids' => [1, 2]],
                'keywords' => ['culture', 'montreal'],
                'video' => ['url' => 'https://www.youtube.com/watch?v=1', 'type' => 'video', 'title' => 'A video', 'provider' => 'YouTube', 'html' => '<iframe></iframe>'],
                'place' => ['lat' => 45.509595, 'lng' => -73.565912, 'address' => 'Place des Arts'],
            ]);
        Http::assertSent(fn (Request $request): bool => $request['url'] === 'https://www.youtube.com/watch?v=1');

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['details' => [
            'rating' => 11,
            'metadata' => '{ "source": ',
            'keywords' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'place' => ['lat' => 120, 'lng' => 0],
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['details.rating', 'details.metadata', 'details.keywords', 'details.place.lat']);
    }

    public function test_update_needs_the_update_ability(): void
    {
        $post = $this->createPost(['title' => 'Locked']);

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'New'])->assertForbidden();
    }

    public function test_if_match(): void
    {
        $post = $this->createPost(['title' => 'Old']);
        $version = $this->getJson(self::API.'/resources/posts/'.$post->id)->json('data.version');

        $response = $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'New'], ['If-Match' => '"'.$version.'"'])->assertOk();
        $newVersion = $response->json('data.version');
        $this->assertNotSame($version, $newVersion);

        // The version the client edited is stale now
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'Other'], ['If-Match' => '"'.$version.'"'])
            ->assertStatus(412)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::VERSION_CONFLICT)
            ->assertJsonPath('current.title', 'New')
            ->assertJsonPath('current.version', $newVersion);
        $this->assertSame('New', $post->refresh()->title);

        // Weak tags, lists and * match too
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'Weak'], ['If-Match' => '"stale", W/"'.$newVersion.'"'])->assertOk();
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'Any'], ['If-Match' => '*'])->assertOk();
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['title' => 'No header'])->assertOk();
    }

    public function test_delete(): void
    {
        $post = $this->createPost();

        $this->deleteJson(self::API.'/resources/posts/'.$post->id)->assertNoContent();
        $this->getJson(self::API.'/resources/posts/'.$post->id)->assertNotFound();
    }

    public function test_delete_needs_the_delete_ability(): void
    {
        $post = $this->createPost(['title' => 'Locked']);

        $this->deleteJson(self::API.'/resources/posts/'.$post->id)->assertForbidden();
        $this->assertNotNull(Post::query()->find($post->id));
    }
}
