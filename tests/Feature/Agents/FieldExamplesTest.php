<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Panneau\Agents\ToolCall;
use Panneau\Agents\Tools\Describe;
use Panneau\Agents\Tools\SaveItem;
use Panneau\Contracts\EmbedResolver;
use Panneau\Fields\Credits;
use Panneau\Fields\Field;
use Panneau\Fields\Sessions;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Models\Asset;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Tag;
use TestApp\Panneau\Resources\ArticlesResource;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * The `example` that `describe` gives for a field whose value isn't a plain scalar: exactly what
 * `save_item` accepts.
 */
class FieldExamplesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Panneau::allowWithoutPolicies();
        Panneau::resources([
            ExamplePostsResource::class, ExampleCompoundsResource::class,
            ImagesResource::class, DocumentsResource::class, ArticlesResource::class,
        ]);
        $this->app->instance(EmbedResolver::class, new class implements EmbedResolver
        {
            public function resolve(string $url): ?array
            {
                return ['url' => $url, 'type' => 'video', 'title' => 'A video', 'provider' => 'YouTube'];
            }
        });
        Author::query()->create(['name' => 'Ada']);
        // The id of a tag is its slug: the examples name items 1 and 2
        Tag::query()->create(['slug' => '1', 'name' => 'News']);
        Tag::query()->create(['slug' => '2', 'name' => 'Tech']);
    }

    public function test_the_fields_of_posts_give_examples_that_save(): void
    {
        $fields = $this->describedFields('example-posts');
        $examples = $this->examples($fields);

        $this->assertSame([
            'dates', 'price', 'author', 'tags',
            'details.reading_time', 'details.metadata', 'details.keywords', 'details.video', 'details.place',
            'details.period', 'details.budget', 'details.venue', 'details.website',
            'address', 'link',
        ], array_keys($examples));
        // None for a plain scalar
        $this->assertArrayNotHasKey('example', $fields['title']);
        $this->assertArrayNotHasKey('example', $fields['featured']);
        $this->assertSame(['amount' => 1250, 'currency' => 'CAD'], $examples['price']);
        $this->assertSame('1', $examples['author']);
        $this->assertSame(['1', '2'], $examples['tags']);
        $this->assertSame(5400, $examples['details.reading_time']);
        $this->assertSame(['start' => '2026-11-04', 'end' => '2026-11-06'], $examples['details.period']);
        $this->assertSame(['type' => 'url', 'url' => 'https://example.com'], $examples['link']);

        $values = ['title' => 'Examples', 'status' => 'draft'];
        foreach ($examples as $path => $example) {
            data_set($values, $path, $example);
        }
        $this->assertSaved('example-posts', $values);
        $post = Post::query()->firstOrFail();
        $this->assertSame('1', (string) $post->author_id);
        $this->assertSame(['street' => '123 Main St', 'city' => 'Montréal', 'region' => 'QC', 'postalCode' => 'H2X 1Y4', 'country' => 'CA'], $post->address);
    }

    public function test_media_give_examples_that_save(): void
    {
        $this->asset('image/png');
        $examples = $this->examples($this->describedFields('articles'));

        $this->assertSame(['cover' => '1', 'gallery' => ['1'], 'attachment' => '1', 'download' => [
            'type' => 'url', 'url' => 'https://example.com', 'label' => ['en' => 'Example'],
        ]], $examples);
        $this->assertSaved('articles', ['title' => 'Examples', ...array_diff_key($examples, ['attachment' => true])]);
    }

    public function test_a_media_of_ids_gives_an_example_that_saves(): void
    {
        $this->asset('application/pdf', ['library' => 'documents']);
        $examples = $this->examples($this->describedFields('articles'));

        $this->assertSaved('articles', ['title' => 'Examples', 'attachment' => $examples['attachment']]);
    }

    public function test_credits_and_sessions_give_examples_that_save(): void
    {
        $examples = $this->examples($this->describedFields('example-compounds'));

        $session = ['start' => '2026-11-04T19:00:00-05:00', 'end' => '2026-11-04T21:00:00-05:00'];
        $this->assertSame([
            'seo' => [['person' => '1', 'roles' => ['1'], 'featured' => true]],
            'blocks' => [['name' => 'Ada Lovelace', 'roles' => 'Director', 'featured' => true]],
            'details' => [['dates' => $session, 'venue' => '1']],
            'link' => [['dates' => $session]],
        ], $examples);
        $this->assertSaved('example-compounds', ['title' => 'Examples', ...$examples]);
    }

    /**
     * The fields `describe` gives a resource, by name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function describedFields(string $resource): array
    {
        $result = $this->app->make(Describe::class)->handle($this->toolCall(['resource' => $resource, 'parts' => ['fields']]));
        $this->assertFalse($result->isError(), (string) json_encode($result->problem));

        return array_column($result->data['fields'] ?? [], null, 'name');
    }

    /**
     * The examples of fields, and of the fields of their groups, by path.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function examples(array $fields, string $prefix = ''): array
    {
        $examples = [];
        foreach ($fields as $name => $field) {
            if (array_key_exists('example', $field)) {
                $examples[$prefix.$name] = $field['example'];
            }
            if ($field['type'] === 'group') {
                $examples = [...$examples, ...$this->examples(array_column($field['fields'], null, 'name'), $prefix.$name.'.')];
            }
        }

        return $examples;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assertSaved(string $resource, array $values): void
    {
        $result = $this->app->make(SaveItem::class)->handle($this->toolCall(['resource' => $resource, 'values' => $values]));

        $this->assertFalse($result->isError(), (string) json_encode($result->problem));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolCall(array $arguments): ToolCall
    {
        $user = new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): GenericUser => $user);

        return new ToolCall($user, $arguments, $request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(string $mime, array $attributes = []): Asset
    {
        $path = 'assets/'.uniqid();
        Storage::disk('public')->put($path, 'contents');

        return Asset::query()->create($attributes + [
            'team_id' => 1,
            'type' => str_starts_with($mime, 'image/') ? 'image' : 'document',
            'file' => ['disk' => 'public', 'path' => $path, 'name' => 'file-'.basename($path), 'mime' => $mime, 'size' => 1000],
        ]);
    }
}

/**
 * The posts, without the password required on create.
 */
class ExamplePostsResource extends PostsResource
{
    public function fields(): array
    {
        return array_values(array_filter(parent::fields(), fn (Field $field): bool => $field->name() !== 'preview_password'));
    }
}

/**
 * Credits and sessions, kept in the JSON columns of posts.
 */
class ExampleCompoundsResource extends Resource
{
    public function model(): string
    {
        return Post::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Credits::make('seo')->people(AuthorsResource::class)->roles(TagsResource::class),
            Credits::make('blocks'),
            Sessions::make('details')->venues(AuthorsResource::class),
            Sessions::make('link'),
        ];
    }
}
