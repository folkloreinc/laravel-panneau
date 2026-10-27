<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Panneau\Fields\Media;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Article;
use TestApp\Models\Asset;
use TestApp\Panneau\Resources\ArticlesResource;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;

/**
 * `media` fields reference media of a library: ArticlesResource references ImagesResource and
 * DocumentsResource.
 */
class MediaFieldTest extends HttpTestCase
{
    use ValidatesProtocol;

    private const TRANSFORM = ['crop' => ['x' => 10, 'y' => 0, 'width' => 1600, 'height' => 900], 'rotate' => 90, 'flipX' => true, 'focalPoint' => ['x' => 0.42, 'y' => 0.35]];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('files');
        Panneau::resources([ImagesResource::class, DocumentsResource::class, ArticlesResource::class]);
        Panneau::allowWithoutPolicies();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(string $mime, array $attributes = [], int $size = 1000): Asset
    {
        $path = 'assets/'.uniqid();
        Storage::disk('public')->put($path, 'contents');

        return Asset::query()->create($attributes + [
            'team_id' => 1,
            'type' => str_starts_with($mime, 'image/') ? 'image' : 'document',
            'file' => ['disk' => 'public', 'path' => $path, 'name' => 'file-'.basename($path), 'mime' => $mime, 'size' => $size],
        ]);
    }

    private function document(): Asset
    {
        return $this->asset('application/pdf', ['library' => 'documents', 'name' => 'Terms']);
    }

    /**
     * Checks the values of media fields against the protocol's `MediaReference`.
     *
     * @param  array<string, mixed>  $item
     */
    private function assertReferences(array $item): void
    {
        foreach (['cover', 'attachment'] as $field) {
            if (isset($item[$field])) {
                $this->assertMatchesComponent($item[$field], 'MediaReference');
            }
        }
        foreach ($item['gallery'] ?? [] as $reference) {
            $this->assertMatchesComponent($reference, 'MediaReference');
        }
    }

    public function test_the_definition_names_the_libraries(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $fields = collect(collect($response->json('resources'))->firstWhere('id', 'articles')['fields'])->keyBy('name');
        $this->assertSame([
            'name' => 'cover',
            'type' => 'media',
            'rules' => [['rule' => 'accept', 'value' => ['image/png', 'image/jpeg']], ['rule' => 'maxSize', 'value' => 500000]],
            'accept' => ['image/png', 'image/jpeg'],
            'resource' => 'images',
        ], $fields['cover']);
        $this->assertSame(['name' => 'gallery', 'type' => 'media', 'rules' => [['rule' => 'max', 'value' => 3]], 'multiple' => true, 'resource' => 'images'], $fields['gallery']);
        $this->assertSame('documents', $fields['attachment']['resource']);
        $this->assertSame('images', $fields['blocks']['types'][0]['fields'][0]['resource']);
    }

    public function test_values_are_stored_and_serialized_as_references(): void
    {
        $cover = $this->asset('image/png', ['name' => 'Portrait', 'alt' => ['fr' => 'Un portrait', 'en' => 'A portrait']]);
        $first = $this->asset('image/jpeg');
        $second = $this->asset('image/png', ['name' => 'Second']);
        $document = $this->document();

        $response = $this->postJson(self::API.'/resources/articles', [
            'title' => 'An article',
            'cover' => ['id' => (string) $cover->id, 'transform' => self::TRANSFORM + ['flipY' => false, 'tool' => ['strength' => 2]]],
            'gallery' => [$first->id, ['id' => (string) $second->id, 'transform' => ['rotate' => 180]]],
            'attachment' => ['id' => (string) $document->id, 'transform' => ['rotate' => 90]],
            'blocks' => [['type' => 'image', 'image' => ['id' => (string) $first->id, 'transform' => ['flipY' => true]]]],
        ])->assertCreated();

        $article = Article::query()->findOrFail($response->json('data.id'));
        // A key an editor tool adds is kept, a false flip is not
        $this->assertSame(['id' => (string) $cover->id, 'transform' => self::TRANSFORM + ['tool' => ['strength' => 2]]], $article->cover);
        $this->assertSame([['id' => (string) $first->id], ['id' => (string) $second->id, 'transform' => ['rotate' => 180]]], $article->gallery);
        // Without transforms, the column holds the id
        $this->assertSame((string) $document->id, $article->attachment);
        $this->assertSame([['type' => 'image', 'image' => ['id' => (string) $first->id, 'transform' => ['flipY' => true]]]], $article->blocks);

        $url = Storage::disk('public')->url($cover->file['path']);
        $response->assertJsonPath('data.cover', [
            'id' => (string) $cover->id,
            'resource' => 'images',
            'label' => 'Portrait',
            'media' => ['type' => 'image', 'mime' => 'image/png', 'size' => 1000, 'url' => $url, 'thumbnailUrl' => $url],
            'transform' => self::TRANSFORM + ['tool' => ['strength' => 2]],
        ]);
        // Without a name, the label is the name of the file
        $response->assertJsonPath('data.gallery.0.label', $first->file['name']);
        $response->assertJsonPath('data.gallery.1.transform', ['rotate' => 180]);
        $response->assertJsonPath('data.attachment.label', 'Terms')->assertJsonPath('data.attachment.media.type', 'document');
        $this->assertArrayNotHasKey('transform', $response->json('data.attachment'));
        // In blocks, values keep their id and transform
        $response->assertJsonPath('data.blocks.0.image', ['id' => (string) $first->id, 'transform' => ['flipY' => true]]);
        $this->assertReferences($response->json('data'));

        $this->getJson(self::API.'/resources/articles/'.$article->id)->assertOk()->assertJsonPath('data.cover.transform', self::TRANSFORM + ['tool' => ['strength' => 2]]);
    }

    public function test_unknown_and_out_of_scope_media_are_refused(): void
    {
        $image = $this->asset('image/png');
        $otherTeam = $this->asset('image/png', ['team_id' => 2]);
        $document = $this->document();
        $deleted = $this->asset('image/png');
        $deleted->delete();

        $errors = fn (array $values): array => array_keys($this->postJson(self::API.'/resources/articles', $values + ['title' => 'A'])->assertUnprocessable()->json('errors'));

        $this->assertSame(['cover'], $errors(['cover' => 'missing']));
        $this->assertSame(['cover'], $errors(['cover' => (string) $otherTeam->id]));
        $this->assertSame(['cover'], $errors(['cover' => (string) $deleted->id]));
        // A document is not in the library of images, an image not in the library of documents
        $this->assertSame(['cover', 'attachment'], $errors(['cover' => (string) $document->id, 'attachment' => (string) $image->id]));
        $this->assertSame(['gallery.1'], $errors(['gallery' => [(string) $image->id, (string) $otherTeam->id]]));
        $this->assertSame(['blocks.0.image'], $errors(['blocks' => [['type' => 'image', 'image' => (string) $otherTeam->id]]]));
        $this->assertSame(['cover'], $errors(['cover' => ['id' => (string) $image->id, 'transform' => ['crop' => ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 10]]]]));
        $this->assertSame(['cover'], $errors(['cover' => ['label' => 'No id']]));

        $this->assertSame(0, Article::query()->count());
    }

    public function test_accept_and_max_size_are_checked_on_the_media(): void
    {
        $gif = $this->asset('image/gif');
        $large = $this->asset('image/png', [], 600_000);

        // What the client says about the media is never used
        $this->postJson(self::API.'/resources/articles', ['title' => 'A', 'cover' => ['id' => (string) $gif->id, 'media' => ['mime' => 'image/png']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The cover must be a file of type: image/png, image/jpeg.']);
        $this->postJson(self::API.'/resources/articles', ['title' => 'A', 'cover' => ['id' => (string) $large->id, 'media' => ['size' => 10]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The cover must not be larger than 500000 bytes.']);
    }

    public function test_the_transform_changes_without_the_media(): void
    {
        $cover = $this->asset('image/png');
        $article = Article::query()->create(['title' => 'A', 'cover' => ['id' => (string) $cover->id, 'transform' => ['rotate' => 90]]]);

        // The reference the item gave, sent back with a new transform
        $reference = $this->getJson(self::API.'/resources/articles/'.$article->id)->assertOk()->json('data.cover');
        $reference['transform'] = ['focalPoint' => ['x' => 0.5, 'y' => 0.5]];
        $this->patchJson(self::API.'/resources/articles/'.$article->id, ['cover' => $reference])
            ->assertOk()
            ->assertJsonPath('data.cover.transform', ['focalPoint' => ['x' => 0.5, 'y' => 0.5]]);
        $this->assertSame(['id' => (string) $cover->id, 'transform' => ['focalPoint' => ['x' => 0.5, 'y' => 0.5]]], $article->refresh()->cover);

        // A media without a transform loses it
        $this->patchJson(self::API.'/resources/articles/'.$article->id, ['cover' => $cover->id])->assertOk();
        $this->assertSame(['id' => (string) $cover->id], $article->refresh()->cover);
        $this->patchJson(self::API.'/resources/articles/'.$article->id, ['cover' => null])->assertOk()->assertJsonPath('data.cover', null);
    }

    public function test_lists_find_the_media_of_a_page_at_once(): void
    {
        $images = [$this->asset('image/png'), $this->asset('image/png'), $this->asset('image/png')];
        foreach (range(1, 5) as $index) {
            Article::query()->create([
                'title' => 'Article '.$index,
                'cover' => ['id' => (string) $images[$index % 3]->id],
                'gallery' => array_map(fn (Asset $image): array => ['id' => (string) $image->id], $images),
            ]);
        }

        DB::enableQueryLog();
        $response = $this->getJson(self::API.'/resources/articles')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertCount(5, $response->json('data'));
        foreach ($response->json('data') as $item) {
            $this->assertReferences($item);
            $this->assertSame('image', $item['cover']['media']['type']);
        }
        $this->assertCount(1, array_filter($queries, fn (string $query): bool => str_starts_with($query, 'select * from "assets"')));
    }

    public function test_media_that_left_the_library_keep_their_id(): void
    {
        $cover = $this->asset('image/png');
        $article = Article::query()->create(['title' => 'A', 'cover' => ['id' => (string) $cover->id, 'transform' => ['rotate' => 90]]]);
        $cover->delete();

        $response = $this->getJson(self::API.'/resources/articles/'.$article->id)->assertOk();

        $response->assertJsonPath('data.cover', ['id' => (string) $cover->id, 'transform' => ['rotate' => 90]]);
        $this->assertMatchesComponent($response->json('data.cover'), 'MediaReference');
    }

    public function test_the_library_can_be_left_out_when_there_is_only_one(): void
    {
        $this->app->forgetInstance(ResourceRegistry::class);
        $this->app['config']->set('panneau.resources', []);
        $articles = new class extends Resource
        {
            public function id(): string
            {
                return 'notes';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), Media::make('cover')];
            }
        };
        Panneau::resources([ImagesResource::class, $articles]);
        $image = $this->asset('image/png');

        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();
        $this->assertValidDefinition($definition);
        $this->assertArrayNotHasKey('resource', collect(collect($definition['resources'])->firstWhere('id', 'notes')['fields'])->firstWhere('name', 'cover'));

        $this->postJson(self::API.'/resources/notes', ['title' => 'A', 'cover' => (string) $image->id])
            ->assertCreated()
            ->assertJsonPath('data.cover.media.type', 'image');
        $this->postJson(self::API.'/resources/notes', ['title' => 'A', 'cover' => 'missing'])->assertUnprocessable();
    }

    public function test_media_items_read_from_the_item_become_references(): void
    {
        $first = $this->asset('image/png', ['name' => 'First']);
        $second = $this->asset('image/png', ['name' => 'Second']);
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'albums';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [
                    Text::make('title'),
                    // Media items, as a relation of the item would give them
                    Media::make('gallery')->resource(ImagesResource::class)->multiple()->resolveUsing(
                        fn (object $article): mixed => Asset::query()->whereIn('id', array_column(data_get($article, 'gallery') ?? [], 'id'))->orderBy('id')->get(),
                    ),
                ];
            }
        }]);
        $album = Article::query()->create(['title' => 'Album', 'gallery' => [['id' => (string) $first->id], ['id' => (string) $second->id]]]);

        $response = $this->getJson(self::API.'/resources/albums/'.$album->id)->assertOk();

        $response->assertJsonPath('data.gallery.0.id', (string) $first->id)
            ->assertJsonPath('data.gallery.0.label', 'First')
            ->assertJsonPath('data.gallery.1.label', 'Second')
            ->assertJsonPath('data.gallery.1.media.type', 'image');
        $this->assertReferences($response->json('data'));
    }
}
