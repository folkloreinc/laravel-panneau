<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Panneau\Fields\File;
use Panneau\Resources\MediaResource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use TestApp\Models\Asset;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;

/**
 * Media libraries on an Eloquent model: ImagesResource and DocumentsResource share the `assets`
 * table, and keep apart by their media types and a `library` column.
 */
class MediaLibraryTest extends HttpTestCase
{
    use MakesUploads;
    use ValidatesProtocol;

    protected function defineEnvironment($app): void
    {
        $this->configureUploads($app);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeUploads();
        Storage::fake('files');
        Storage::fake('public');
        Panneau::resources([ImagesResource::class, DocumentsResource::class, self::allMedia()]);
        Panneau::allowWithoutPolicies();
        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A library of every type, on the same table, whose `accept` doesn't follow its types.
     */
    private static function allMedia(): MediaResource
    {
        return new class extends MediaResource
        {
            public function id(): string
            {
                return 'all-media';
            }

            public function model(): string
            {
                return Asset::class;
            }

            protected function file(File $file): File
            {
                return $file->disk('files');
            }

            public function fields(): array
            {
                return [];
            }

            public function scope(Request $request): array
            {
                return ['team_id' => 1];
            }
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(string $type, array $attributes = []): Asset
    {
        $mime = ['image' => 'image/png', 'document' => 'application/pdf', 'video' => 'video/mp4'][$type];
        $path = 'assets/'.uniqid().'.'.explode('/', $mime)[1];
        Storage::disk('public')->put($path, 'contents');

        return Asset::query()->create($attributes + [
            'team_id' => 1,
            'type' => $type,
            'name' => ucfirst($type),
            'file' => ['disk' => 'public', 'path' => $path, 'name' => basename($path), 'mime' => $mime, 'size' => 8],
        ]);
    }

    public function test_the_definition_describes_libraries(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $images = collect($response->json('resources'))->firstWhere('id', 'images');
        $this->assertSame('media', $images['kind']);
        $this->assertSame(['file', 'name', 'alt', 'transform'], array_column($images['fields'], 'name'));
        $this->assertSame([
            'name' => 'file',
            'type' => 'file',
            'rules' => [['rule' => 'required'], ['rule' => 'maxSize', 'value' => 1000000], ['rule' => 'accept', 'value' => ['image/*']]],
            'accept' => ['image/*'],
        ], $images['fields'][0]);
        $this->assertSame([
            'id' => 'type',
            'type' => 'select',
            'label' => 'Type',
            'options' => [['value' => 'image', 'label' => 'Images']],
            'multiple' => true,
        ], $images['list']['filters'][0]);

        $all = collect($response->json('resources'))->firstWhere('id', 'all-media');
        $this->assertArrayNotHasKey('accept', $all['fields'][0]);
        $this->assertSame(['image', 'video', 'audio', 'document', 'font', 'other'], array_column($all['list']['filters'][0]['options'], 'value'));
        $this->assertArrayNotHasKey('kind', collect($response->json('resources'))->firstWhere('id', 'posts'));
    }

    public function test_a_media_is_made_from_an_upload(): void
    {
        $upload = $this->upload('portrait.png', self::png(), 'image/png');

        $response = $this->postJson(self::API.'/resources/images', [
            'file' => ['upload' => $upload],
            'name' => 'Portrait',
            'media' => ['type' => 'video', 'url' => 'https://evil.test'],
        ])->assertCreated();

        $asset = Asset::query()->findOrFail($response->json('data.id'));
        $this->assertSame(1, $asset->team_id);
        $this->assertSame('image', $asset->type);
        $this->assertNull($asset->library);
        $this->assertSame('Portrait', $asset->name);
        $path = $asset->file['path'];
        $this->assertStringStartsWith('images/', $path);
        Storage::disk('public')->assertExists($path);

        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        $url = Storage::disk('public')->url($path);
        $response
            ->assertJsonPath('data.file', ['name' => 'portrait.png', 'url' => $url, 'mime' => 'image/png', 'size' => strlen(self::png())])
            ->assertJsonPath('data.media', [
                'type' => 'image',
                'mime' => 'image/png',
                'size' => strlen(self::png()),
                'url' => $url,
                'thumbnailUrl' => $url,
            ]);
    }

    public function test_a_media_is_made_from_a_file_in_the_request(): void
    {
        $response = $this->post(self::API.'/resources/documents', [
            'file' => UploadedFile::fake()->createWithContent('terms.pdf', self::PDF),
        ], ['Accept' => 'application/json'])->assertCreated();

        $asset = Asset::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['team_id' => 1, 'library' => 'documents', 'type' => 'document'], $asset->only(['team_id', 'library', 'type']));
        Storage::disk('files')->assertExists($asset->file['path']);
        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        // A private file gets a temporary URL; a document has no thumbnail
        $response
            ->assertJsonPath('data.media.type', 'document')
            ->assertJsonPath('data.media.mime', 'application/pdf')
            ->assertJsonPath('data.media.thumbnailUrl', null);
        $this->assertStringContainsString('expiration=', (string) $response->json('data.media.url'));
    }

    public function test_a_library_refuses_files_of_other_types(): void
    {
        $this->postJson(self::API.'/resources/images', ['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->postJson(self::API.'/resources/documents', ['file' => ['upload' => $this->upload('photo.png', self::png(), 'image/png')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->postJson(self::API.'/resources/images', [])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, Asset::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_media_types_of_the_scope_are_checked_when_the_media_is_made(): void
    {
        // An image library that accepts PDF files still refuses them: they are documents
        $library = new class extends ImagesResource
        {
            public function id(): string
            {
                return 'loose-images';
            }

            protected function file(File $file): File
            {
                return parent::file($file)->accept(['image/*', 'application/pdf']);
            }
        };
        Panneau::resources([$library]);

        $this->postJson(self::API.'/resources/loose-images', ['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file must be a file of type: image.']);

        $this->assertSame(0, Asset::query()->count());
        // The stored file is deleted when the save fails
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_file_of_a_media_is_replaced(): void
    {
        $created = $this->postJson(self::API.'/resources/images', ['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->assertCreated();
        $asset = Asset::query()->findOrFail($created->json('data.id'));
        $before = $asset->file['path'];

        $response = $this->patchJson(self::API.'/resources/images/'.$asset->id, [
            'file' => ['upload' => $this->upload('b.png', self::png(), 'image/png')],
        ])->assertOk();

        $after = $asset->refresh()->file['path'];
        $this->assertNotSame($before, $after);
        Storage::disk('public')->assertMissing($before);
        Storage::disk('public')->assertExists($after);
        $response->assertJsonPath('data.file.name', 'b.png')->assertJsonPath('data.media.url', Storage::disk('public')->url($after));
        $this->assertMatchesComponent($response->json('data'), 'MediaItem');

        // Its metadata changes without touching the file
        $this->patchJson(self::API.'/resources/images/'.$asset->id, ['name' => 'B', 'alt' => ['fr' => 'Bé', 'en' => 'Bee']])
            ->assertOk()
            ->assertJsonPath('data.name', 'B')
            ->assertJsonPath('data.file.name', 'b.png');
    }

    public function test_lists_filter_by_type(): void
    {
        $image = $this->asset('image');
        $document = $this->asset('document');
        $video = $this->asset('video');

        $ids = fn (string $query): array => array_column($this->getJson(self::API.'/resources/all-media'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([(string) $image->id, (string) $document->id, (string) $video->id], $ids(''));
        $this->assertSame([(string) $image->id], $ids('?filter[type]=image'));
        $this->assertSame([(string) $image->id, (string) $video->id], $ids('?filter[type][]=image&filter[type][]=video'));
        $this->assertSame([], $ids('?filter[type]=font'));
        $this->assertSame([(string) $image->id, (string) $document->id, (string) $video->id], $ids('?filter[type]=Not a type'));

        $response = $this->getJson(self::API.'/resources/all-media?filter[type]=video&fields=id')->assertOk();
        // Items of a library always have their media
        $this->assertMatchesComponent($response->json('data.0'), 'MediaItem');
        $response->assertJsonPath('data.0.media.type', 'video')->assertJsonPath('data.0.media.thumbnailUrl', null);
    }

    public function test_libraries_only_reach_media_of_their_scope(): void
    {
        $image = $this->asset('image');
        $document = $this->asset('document', ['library' => 'documents']);
        $looseDocument = $this->asset('document');
        $otherTeam = $this->asset('image', ['team_id' => 2]);

        $ids = fn (string $library, string $query = ''): array => array_column($this->getJson(self::API.'/resources/'.$library.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([(string) $image->id], $ids('images'));
        $this->assertSame([], $ids('images', '?filter[type]=document'));
        $this->assertSame([(string) $document->id], $ids('documents'));
        $this->assertSame([(string) $image->id, (string) $document->id, (string) $looseDocument->id], $ids('all-media'));

        $this->getJson(self::API.'/resources/images/'.$image->id)->assertOk();
        $this->getJson(self::API.'/resources/images/'.$document->id)->assertNotFound();
        $this->getJson(self::API.'/resources/documents/'.$looseDocument->id)->assertNotFound();
        $this->getJson(self::API.'/resources/images/'.$otherTeam->id)->assertNotFound();
        $this->patchJson(self::API.'/resources/images/'.$otherTeam->id, ['name' => 'Mine'])->assertNotFound();
    }

    public function test_a_media_is_deleted_and_kept_in_the_trash(): void
    {
        $image = $this->asset('image');

        $this->deleteJson(self::API.'/resources/images/'.$image->id)->assertNoContent();

        $this->assertSoftDeleted($image);
        $this->getJson(self::API.'/resources/images/'.$image->id)->assertNotFound();
    }
}
