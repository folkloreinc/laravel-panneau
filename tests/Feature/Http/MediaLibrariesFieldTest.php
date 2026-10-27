<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Panneau\Fields\BlockType;
use Panneau\Fields\Media;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Resources\MediaResource;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Article;
use TestApp\Models\Asset;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Repositories\Adapters\ArrayRepository;

/**
 * `media` fields that allow several libraries (panneau-js decision 0027): ids are unique only
 * within their library, so values carry their `resource`.
 */
class MediaLibrariesFieldTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('files');
        Panneau::allowWithoutPolicies();
    }

    /**
     * Articles whose media fields allow the given libraries, or every library with none.
     *
     * @param  list<string>  $libraries
     */
    private function articles(array $libraries): Resource
    {
        return new class($libraries) extends Resource
        {
            /**
             * @param  list<string>  $libraries
             */
            public function __construct(private readonly array $libraries) {}

            public function id(): string
            {
                return 'dossiers';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                $media = fn (string $name): Media => count($this->libraries) > 0 ? Media::make($name)->resource($this->libraries) : Media::make($name);

                return [
                    Text::make('title'),
                    $media('cover'),
                    $media('gallery')->multiple(),
                    Repeater::make('blocks')->types([BlockType::make('media', 'Media', [$media('media')])]),
                ];
            }
        };
    }

    /**
     * A library that keeps its media in memory: its ids are those of the assets too.
     */
    private function archives(): MediaResource
    {
        $this->app->instance(ArrayRepository::class, new ArrayRepository([
            '1' => (object) ['id' => '1', 'name' => 'Archived report', 'type' => 'document', 'file' => ['disk' => 'files', 'path' => 'archives/report.pdf', 'name' => 'report.pdf', 'mime' => 'application/pdf', 'size' => 2000]],
        ]));

        return new class extends MediaResource
        {
            public function id(): string
            {
                return 'archives';
            }

            public function repository(): string
            {
                return ArrayRepository::class;
            }

            public function itemTitle(): string
            {
                return 'name';
            }

            public function fields(): array
            {
                return [Text::make('name')];
            }
        };
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

    private function document(string $name = 'Terms'): Asset
    {
        return $this->asset('application/pdf', ['library' => 'documents', 'name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function assertReferences(array $item): void
    {
        if (isset($item['cover'])) {
            $this->assertMatchesComponent($item['cover'], 'MediaReference');
        }
        foreach ($item['gallery'] ?? [] as $reference) {
            $this->assertMatchesComponent($reference, 'MediaReference');
        }
    }

    public function test_the_definition_lists_the_libraries(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([ImagesResource::class, 'documents'])]);

        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();

        $this->assertValidDefinition($definition);
        $fields = collect(collect($definition['resources'])->firstWhere('id', 'dossiers')['fields'])->keyBy('name');
        $this->assertSame(['images', 'documents'], $fields['cover']['resource']);
        $this->assertSame(['images', 'documents'], $fields['gallery']['resource']);
        $this->assertSame(['images', 'documents'], $fields['blocks']['types'][0]['fields'][0]['resource']);
    }

    public function test_values_keep_their_library(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([ImagesResource::class, DocumentsResource::class])]);
        $image = $this->asset('image/png', ['name' => 'Portrait']);
        $document = $this->document();

        $response = $this->postJson(self::API.'/resources/dossiers', [
            'title' => 'A dossier',
            'cover' => ['id' => (string) $document->id, 'resource' => 'documents', 'transform' => ['rotate' => 90]],
            'gallery' => [['id' => (string) $image->id, 'resource' => 'images'], ['id' => (string) $document->id, 'resource' => 'documents']],
            'blocks' => [['type' => 'media', 'media' => ['id' => (string) $image->id, 'resource' => 'images']]],
        ])->assertCreated();

        $article = Article::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['id' => (string) $document->id, 'resource' => 'documents', 'transform' => ['rotate' => 90]], $article->cover);
        $this->assertSame([['id' => (string) $image->id, 'resource' => 'images'], ['id' => (string) $document->id, 'resource' => 'documents']], $article->gallery);
        $this->assertSame([['type' => 'media', 'media' => ['id' => (string) $image->id, 'resource' => 'images']]], $article->blocks);

        $this->assertSame(['id', 'resource', 'label', 'media', 'transform'], array_keys($response->json('data.cover')));
        $response->assertJsonPath('data.cover.id', (string) $document->id)
            ->assertJsonPath('data.cover.resource', 'documents')
            ->assertJsonPath('data.cover.label', 'Terms')
            ->assertJsonPath('data.cover.media.type', 'document')
            ->assertJsonPath('data.cover.media.mime', 'application/pdf')
            ->assertJsonPath('data.cover.transform', ['rotate' => 90]);
        $response->assertJsonPath('data.gallery.0.resource', 'images')
            ->assertJsonPath('data.gallery.0.label', 'Portrait')
            ->assertJsonPath('data.gallery.0.media.type', 'image')
            ->assertJsonPath('data.gallery.1.resource', 'documents')
            ->assertJsonPath('data.gallery.1.media.type', 'document');
        // In blocks, values keep their id and library
        $response->assertJsonPath('data.blocks.0.media', ['id' => (string) $image->id, 'resource' => 'images']);
        $this->assertReferences($response->json('data'));

        // The references the item gave can be sent back
        $this->patchJson(self::API.'/resources/dossiers/'.$article->id, ['gallery' => $response->json('data.gallery')])
            ->assertOk()
            ->assertJsonPath('data.gallery.1.resource', 'documents');
        $this->assertSame([['id' => (string) $image->id, 'resource' => 'images'], ['id' => (string) $document->id, 'resource' => 'documents']], $article->refresh()->gallery);
    }

    public function test_each_media_names_one_of_the_libraries(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([ImagesResource::class, DocumentsResource::class])]);
        $image = $this->asset('image/png');
        $document = $this->document();

        $errors = fn (array $values): array => $this->postJson(self::API.'/resources/dossiers', $values + ['title' => 'A'])->assertUnprocessable()->json('errors');

        // An id alone, or without a resource
        $this->assertSame(['cover'], array_keys($errors(['cover' => (string) $image->id])));
        $this->assertSame(
            ['The cover allows several libraries: name the library.'],
            $errors(['cover' => ['id' => (string) $image->id]])['cover'] ?? null,
        );
        $this->assertSame(['gallery.1'], array_keys($errors(['gallery' => [['id' => (string) $image->id, 'resource' => 'images'], (string) $document->id]])));
        $this->assertSame(['blocks.0.media'], array_keys($errors(['blocks' => [['type' => 'media', 'media' => (string) $image->id]]])));
        // A resource that isn't one of the field's libraries
        $this->assertSame(
            ['The library of the cover is not one this field allows.'],
            $errors(['cover' => ['id' => (string) $image->id, 'resource' => 'posts']])['cover'] ?? null,
        );
        $this->assertSame(['cover'], array_keys($errors(['cover' => ['id' => (string) $image->id, 'resource' => 12]])));
        // The media is looked up in the library it names
        $this->assertSame(['cover'], array_keys($errors(['cover' => ['id' => (string) $image->id, 'resource' => 'documents']])));

        $this->assertSame(0, Article::query()->count());
    }

    public function test_the_same_id_in_two_libraries(): void
    {
        $archives = $this->archives();
        Panneau::resources([ImagesResource::class, $archives, $this->articles([ImagesResource::class, 'archives'])]);
        $image = $this->asset('image/png', ['name' => 'Portrait']);
        $this->assertSame('1', (string) $image->id);

        $response = $this->postJson(self::API.'/resources/dossiers', [
            'title' => 'A',
            'gallery' => [['id' => '1', 'resource' => 'archives'], ['id' => '1', 'resource' => 'images']],
        ])->assertCreated();

        $response->assertJsonPath('data.gallery.0.resource', 'archives')
            ->assertJsonPath('data.gallery.0.label', 'Archived report')
            ->assertJsonPath('data.gallery.0.media.type', 'document')
            ->assertJsonPath('data.gallery.1.resource', 'images')
            ->assertJsonPath('data.gallery.1.label', 'Portrait')
            ->assertJsonPath('data.gallery.1.media.type', 'image');
        $this->assertReferences($response->json('data'));
    }

    public function test_a_field_without_libraries_allows_every_library(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([])]);
        $image = $this->asset('image/png');
        $document = $this->document();

        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();
        $this->assertValidDefinition($definition);
        $this->assertArrayNotHasKey('resource', collect(collect($definition['resources'])->firstWhere('id', 'dossiers')['fields'])->firstWhere('name', 'cover'));

        $this->postJson(self::API.'/resources/dossiers', [
            'title' => 'A',
            'cover' => ['id' => (string) $image->id, 'resource' => 'images'],
            'gallery' => [['id' => (string) $document->id, 'resource' => 'documents']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.cover.resource', 'images')
            ->assertJsonPath('data.cover.media.type', 'image')
            ->assertJsonPath('data.gallery.0.resource', 'documents')
            ->assertJsonPath('data.gallery.0.media.type', 'document');
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => (string) $image->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The cover allows several libraries: name the library.']);
    }

    public function test_scope_accept_and_max_size_are_checked_on_the_media_found(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, new class extends Resource
        {
            public function id(): string
            {
                return 'dossiers';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [
                    Text::make('title'),
                    Media::make('cover')->resource(ImagesResource::class, DocumentsResource::class)->accept(['image/png', 'application/pdf'])->maxSize(500_000),
                ];
            }
        }]);
        $otherTeam = $this->asset('image/png', ['team_id' => 2]);
        $gif = $this->asset('image/gif');
        $large = $this->asset('application/pdf', ['library' => 'documents'], 600_000);
        $document = $this->document();

        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => ['id' => (string) $otherTeam->id, 'resource' => 'images']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The selected cover is invalid.']);
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => ['id' => (string) $gif->id, 'resource' => 'images', 'media' => ['mime' => 'image/png']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The cover must be a file of type: image/png, application/pdf.']);
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => ['id' => (string) $large->id, 'resource' => 'documents']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The cover must not be larger than 500000 bytes.']);
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => ['id' => (string) $document->id, 'resource' => 'documents']])->assertCreated();
    }

    public function test_a_field_with_one_library_keeps_plain_ids(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([ImagesResource::class])]);
        $image = $this->asset('image/png');

        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();
        $this->assertSame('images', collect(collect($definition['resources'])->firstWhere('id', 'dossiers')['fields'])->firstWhere('name', 'cover')['resource']);

        // An id, or a value that names the field's library, which the repository doesn't need
        $response = $this->postJson(self::API.'/resources/dossiers', [
            'title' => 'A',
            'cover' => (string) $image->id,
            'gallery' => [['id' => (string) $image->id, 'resource' => 'images']],
        ])->assertCreated();

        $article = Article::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['id' => (string) $image->id], $article->cover);
        $this->assertSame([['id' => (string) $image->id]], $article->gallery);
        $response->assertJsonPath('data.cover.resource', 'images')->assertJsonPath('data.gallery.0.resource', 'images');
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'cover' => ['id' => (string) $image->id, 'resource' => 'documents']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover' => 'The library of the cover is not one this field allows.']);
    }

    public function test_lists_find_the_media_of_several_libraries_in_one_query_per_library(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, $this->articles([ImagesResource::class, DocumentsResource::class])]);
        $images = [$this->asset('image/png'), $this->asset('image/png')];
        $documents = [$this->document('First'), $this->document('Second')];
        foreach (range(1, 4) as $index) {
            Article::query()->create([
                'title' => 'Article '.$index,
                'cover' => ['id' => (string) $documents[$index % 2]->id, 'resource' => 'documents'],
                'gallery' => array_map(fn (Asset $image): array => ['id' => (string) $image->id, 'resource' => 'images'], $images),
            ]);
        }

        DB::enableQueryLog();
        $response = $this->getJson(self::API.'/resources/dossiers')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertCount(4, $response->json('data'));
        foreach ($response->json('data') as $item) {
            $this->assertReferences($item);
            $this->assertSame('documents', $item['cover']['resource']);
            $this->assertSame('document', $item['cover']['media']['type']);
            $this->assertSame(['images', 'images'], array_column($item['gallery'], 'resource'));
        }
        // One query for the images, one for the documents
        $this->assertCount(2, array_filter($queries, fn (string $query): bool => str_starts_with($query, 'select * from "assets"')));
    }

    public function test_media_items_become_references_of_their_library(): void
    {
        $archives = $this->archives();
        $image = $this->asset('image/png', ['name' => 'Portrait']);
        Panneau::resources([ImagesResource::class, $archives, new class extends Resource
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
                    Media::make('gallery')->resource('archives', ImagesResource::class)->multiple()->resolveUsing(
                        fn (object $article): mixed => Asset::query()->whereIn('id', array_column(data_get($article, 'gallery') ?? [], 'id'))->get(),
                    ),
                ];
            }
        }]);
        $album = Article::query()->create(['title' => 'Album', 'gallery' => [['id' => (string) $image->id]]]);

        $response = $this->getJson(self::API.'/resources/albums/'.$album->id)->assertOk();

        // An asset is an item of the images, the library of the field whose items are assets
        $response->assertJsonPath('data.gallery.0.resource', 'images')
            ->assertJsonPath('data.gallery.0.label', 'Portrait');
        $this->assertReferences($response->json('data'));
    }

    public function test_a_field_that_keeps_ids_needs_one_library(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, new class extends Resource
        {
            public function id(): string
            {
                return 'dossiers';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), Media::make('attachment')->transforms(false)];
            }
        }]);
        $this->withoutExceptionHandling();

        $this->expectExceptionMessage('The media field "attachment" keeps ids only (transforms(false)), so it allows one library only: name it with resource().');
        $this->postJson(self::API.'/resources/dossiers', ['title' => 'A', 'attachment' => '1']);
    }
}
