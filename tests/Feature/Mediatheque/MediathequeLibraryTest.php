<?php

namespace Panneau\Tests\Feature\Mediatheque;

use Folklore\Image\ImageServiceProvider;
use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Mediatheque\Events\MediaCreated;
use Folklore\Mediatheque\Facades\Mediatheque as MediathequeFacade;
use Folklore\Mediatheque\Ingest\Source;
use Folklore\Mediatheque\Jobs\ProbeMedia;
use Folklore\Mediatheque\Models\File as MediathequeFile;
use Folklore\Mediatheque\Models\Media;
use Folklore\Mediatheque\Models\PipelineJob;
use Folklore\Mediatheque\Models\PipelineRun;
use Folklore\Mediatheque\ServiceProvider as MediathequeServiceProvider;
use Folklore\Mediatheque\Support\Models;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use Panneau\Fields\File;
use Panneau\Fields\Media as MediaField;
use Panneau\Fields\Text;
use Panneau\Mediatheque\MediathequeRepository;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use Panneau\Tests\Feature\Http\HttpTestCase;
use TestApp\Models\Article;
use TestApp\Panneau\Resources\MediaLibraryResource;

/**
 * A library on laravel-mediatheque 2. Skipped when the mediatheque is not installed.
 */
class MediathequeLibraryTest extends HttpTestCase
{
    use MakesUploads;
    use ValidatesProtocol;

    private const MIGRATIONS = __DIR__.'/../../../vendor/folklore/laravel-mediatheque/database/migrations';

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ...(class_exists(MediathequeServiceProvider::class) ? [MediathequeServiceProvider::class] : []),
            ...(class_exists(ImageServiceProvider::class) ? [ImageServiceProvider::class] : []),
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->configureUploads($app);
        $app['config']->set('filesystems.disks.media', ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://media.test']);
        $app['config']->set('mediatheque.source', 'media');
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        if (is_dir(self::MIGRATIONS)) {
            $this->loadMigrationsFrom(self::MIGRATIONS);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(Mediatheque::class)) {
            $this->markTestSkipped('folklore/laravel-mediatheque is not installed.');
        }
        // The application's own column on the media
        Schema::table(Models::table('medias'), function (Blueprint $table): void {
            $table->unsignedInteger('team_id')->nullable();
        });
        $this->fakeUploads();
        Storage::fake('media');
        Bus::fake();
        Panneau::resources([MediaLibraryResource::class]);
        Panneau::allowWithoutPolicies();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function create(array $values, string $library = 'media-library'): TestResponse
    {
        return $this->postJson(self::API.'/resources/'.$library, $values);
    }

    public function test_the_definition_describes_the_library(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $library = collect($response->json('resources'))->firstWhere('id', 'media-library');
        $this->assertSame('media', $library['kind']);
        // The mediatheque's types give the accepted files
        $this->assertSame([
            'name' => 'file',
            'type' => 'file',
            'rules' => [['rule' => 'required'], ['rule' => 'accept', 'value' => ['image/*', 'application/pdf']]],
            'accept' => ['image/*', 'application/pdf'],
        ], $library['fields'][0]);
        $this->assertSame(['image', 'document'], array_column($library['list']['filters'][0]['options'], 'value'));
    }

    public function test_a_media_is_ingested_from_an_upload(): void
    {
        $upload = $this->upload('portrait.png', self::png(), 'image/png');

        $response = $this->create(['file' => ['upload' => $upload], 'name' => 'Portrait'])->assertCreated();

        $media = Media::query()->where('uid', $response->json('data.id'))->firstOrFail();
        $this->assertStringStartsWith('med_', $media->uid);
        $this->assertSame(['type' => 'image', 'name' => 'Portrait', 'team_id' => 1], $media->only(['type', 'name', 'team_id']));
        $original = $media->original();
        $this->assertNotNull($original);
        Storage::disk('media')->assertExists((string) $original->path());
        Bus::assertDispatched(ProbeMedia::class);

        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        $url = Storage::disk('media')->url((string) $original->path());
        $response
            ->assertJsonPath('data.name', 'Portrait')
            ->assertJsonPath('data.file', ['name' => 'portrait.png', 'url' => $url, 'mime' => 'image/png', 'size' => strlen(self::png())])
            ->assertJsonPath('data.media.type', 'image')
            ->assertJsonPath('data.media.mime', 'image/png')
            ->assertJsonPath('data.media.url', $url)
            ->assertJsonPath('data.media.thumbnailUrl', $url)
            ->assertJsonPath('data.media.status', 'pending')
            ->assertJsonPath('data.media.metadata.filename', 'portrait.png');
    }

    public function test_a_media_is_ingested_from_a_file_in_the_request(): void
    {
        $response = $this->post(self::API.'/resources/media-library', [
            'file' => UploadedFile::fake()->createWithContent('terms.pdf', self::PDF),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        // Named after its file
        $response->assertJsonPath('data.name', 'terms.pdf')->assertJsonPath('data.media.type', 'document');
    }

    public function test_thumbnails_of_images_are_laravel_image_urls(): void
    {
        if (! class_exists(ImageServiceProvider::class)) {
            $this->markTestSkipped('folklore/laravel-image is not installed.');
        }
        $response = $this->post(self::API.'/resources/media-library', [
            'file' => UploadedFile::fake()->createWithContent('photo.png', self::png()),
        ], ['Accept' => 'application/json'])->assertCreated();

        // A signed URL of the media's image, 480 pixels wide, that names the media, not its path
        $thumbnail = (string) $response->json('data.media.thumbnailUrl');
        $this->assertStringContainsString('/media/'.$response->json('data.id'), $thumbnail);
        $this->assertStringContainsString('w480', $thumbnail);
        $this->assertNotSame($response->json('data.media.url'), $thumbnail);
    }

    /**
     * The documented way to test a library: a real ingest, on a fake disk, without its jobs.
     */
    public function test_a_library_is_tested_with_a_real_ingest(): void
    {
        Event::fake([MediaCreated::class]);

        $this->create(['file' => ['upload' => $this->upload('portrait.png', self::png(), 'image/png')], 'name' => 'Portrait'])
            ->assertCreated();

        Event::assertDispatched(MediaCreated::class, fn (MediaCreated $event): bool => $event->media->name() === 'Portrait');
        Bus::assertDispatched(ProbeMedia::class);
        $this->assertDatabaseHas(Models::table('medias'), ['name' => 'Portrait', 'type' => 'image', 'team_id' => 1]);
    }

    public function test_the_fake_of_the_mediatheque_is_refused_with_an_explanation(): void
    {
        MediathequeFacade::fake();
        $this->withoutExceptionHandling();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A media library stores its media in the database, as '.Media::class.', and Mediatheque::fake() keeps them in memory.');

        $this->create(['file' => ['upload' => $this->upload('portrait.png', self::png(), 'image/png')]]);
    }

    public function test_files_outside_the_library_leave_nothing_behind(): void
    {
        // The upload claims to be an image: the mediatheque reads its content
        $this->create(['file' => ['upload' => $this->upload('fake.png', 'not an image', 'image/png')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        // A library of images that accepts PDF files still refuses documents
        Panneau::resources([new class extends MediaLibraryResource
        {
            public function id(): string
            {
                return 'images';
            }

            public function mediaTypes(): array
            {
                return ['image'];
            }

            protected function file(File $file): File
            {
                return $file->accept(['image/*', 'application/pdf']);
            }
        }]);
        $this->create(['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]], 'images')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file must be a file of type: image.']);

        $this->assertSame(0, Media::query()->withTrashed()->count());
        $this->assertSame(0, MediathequeFile::query()->count());
        $this->assertSame([], Storage::disk('media')->allFiles());
        Bus::assertNotDispatched(ProbeMedia::class);
    }

    /**
     * Another image than `png()`: a 2×2 red square.
     */
    private static function redSquare(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEElEQVR4nGP4z8AARAwQCgAf7gP9i18U1AAAAABJRU5ErkJggg==');
    }

    private function media(string $id): Media
    {
        return Media::query()->where('uid', $id)->firstOrFail()->load('mediaFiles');
    }

    public function test_the_file_of_a_media_is_replaced_by_an_upload(): void
    {
        $id = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')], 'name' => 'Portrait'])->assertCreated()->json('data.id');
        $before = $this->media($id);
        $path = $before->original()?->path();

        $response = $this->patchJson(self::API.'/resources/media-library/'.$id, [
            'file' => ['upload' => $this->upload('b.png', self::redSquare(), 'image/png')],
        ])->assertOk();

        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        $response
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Portrait')
            ->assertJsonPath('data.file.name', 'b.png')
            ->assertJsonPath('data.media.status', 'pending');
        $after = $this->media($id);
        $this->assertSame($before->getKey(), $after->getKey());
        $this->assertNotSame($path, $after->original()?->path());
        Storage::disk('media')->assertExists((string) $after->original()?->path());
        $this->assertSame(1, MediathequeFile::query()->where('handle', 'original')->count());
    }

    public function test_the_file_of_a_media_is_replaced_by_a_file_in_the_request(): void
    {
        $id = $this->create(['file' => ['upload' => $this->upload('a.pdf', self::PDF, 'application/pdf')]])->assertCreated()->json('data.id');

        $this->patch(self::API.'/resources/media-library/'.$id, [
            'file' => UploadedFile::fake()->createWithContent('b.pdf', self::PDF.'%% new'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.file.name', 'b.pdf')
            ->assertJsonPath('data.media.type', 'document');
    }

    public function test_the_original_is_kept_when_it_is_sent_back_or_left_out(): void
    {
        $created = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->assertCreated();
        $id = $created->json('data.id');
        $path = $this->media($id)->original()?->path();

        $this->patchJson(self::API.'/resources/media-library/'.$id, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
        $this->patchJson(self::API.'/resources/media-library/'.$id, ['name' => 'Again', 'file' => $created->json('data.file')])
            ->assertOk()
            ->assertJsonPath('data.file.name', 'a.png');

        $this->assertSame($path, $this->media($id)->original()?->path());
        $this->assertSame(1, MediathequeFile::query()->count());
    }

    public function test_a_replacement_the_mediatheque_refuses_leaves_the_media_as_it_was(): void
    {
        $id = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->assertCreated()->json('data.id');
        $path = $this->media($id)->original()?->path();
        $files = Storage::disk('media')->allFiles();

        // Not an image, whatever it claims; and a document for an image
        $this->patchJson(self::API.'/resources/media-library/'.$id, ['file' => ['upload' => $this->upload('fake.png', 'not an image', 'image/png')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->patchJson(self::API.'/resources/media-library/'.$id, ['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame($path, $this->media($id)->original()?->path());
        $this->assertSame('image', $this->media($id)->type());
        $this->assertSame($files, Storage::disk('media')->allFiles());
    }

    public function test_a_library_can_allow_type_changes_within_its_types(): void
    {
        Panneau::resources([new class extends MediaLibraryResource
        {
            public function id(): string
            {
                return 'images';
            }

            public function mediaTypes(): array
            {
                return ['image'];
            }

            protected function file(File $file): File
            {
                return $file->accept(['image/*', 'application/pdf']);
            }

            public function repository(): ?string
            {
                return TypeChangingRepository::class;
            }
        }, new class extends MediaLibraryResource
        {
            public function id(): string
            {
                return 'anything';
            }

            public function repository(): ?string
            {
                return TypeChangingRepository::class;
            }
        }]);
        $image = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]], 'images')->assertCreated()->json('data.id');
        $files = Storage::disk('media')->allFiles();

        // The mediatheque allows the new type, but the library only holds images
        $this->patchJson(self::API.'/resources/images/'.$image, ['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file must be a file of type: image.']);
        $this->assertSame('image', $this->media($image)->type());
        $this->assertSame($files, Storage::disk('media')->allFiles());

        // A library of images and documents takes it
        $other = $this->create(['file' => ['upload' => $this->upload('b.png', self::png(), 'image/png')]], 'anything')->assertCreated()->json('data.id');
        $this->patchJson(self::API.'/resources/anything/'.$other, ['file' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]])
            ->assertOk()
            ->assertJsonPath('data.media.type', 'document');
    }

    public function test_a_failed_media_says_why(): void
    {
        $id = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->assertCreated()->json('data.id');
        $media = Media::query()->where('uid', $id)->firstOrFail();
        $run = fn (string $status, ?array $error): PipelineRun => tap((new PipelineRun)->forceFill([
            'uid' => 'run_'.Str::lower((string) Str::ulid()),
            'name' => 'image',
            'pipelinable_type' => $media->getMorphClass(),
            'pipelinable_id' => $media->getKey(),
            'transcoder' => 'ffmpeg',
            'definition' => [],
            'output_source' => 'media',
            'path_prefix' => 'renditions',
            'visibility' => 'public',
            'status' => $status,
            'error' => $error,
        ]))->save();

        // Ready: no error
        $this->getJson(self::API.'/resources/media-library/'.$id)->assertOk()->assertJsonMissingPath('data.media.error');

        // The run's own error, from its latest failed run
        $run('failed', ['code' => 'input_unreadable', 'message' => 'The file is unreadable.']);
        $run('failed', ['code' => 'timeout', 'message' => 'The poster took too long.']);
        $media->forceFill(['status' => 'failed'])->save();
        $response = $this->getJson(self::API.'/resources/media-library/'.$id)->assertOk();
        $this->assertMatchesComponent($response->json('data'), 'MediaItem');
        $response->assertJsonPath('data.media.status', 'failed')
            ->assertJsonPath('data.media.error', ['code' => 'timeout', 'message' => 'The poster took too long.']);

        // Without one, the error of its failed rendition
        $failed = $run('failed', null);
        (new PipelineJob)->forceFill([
            'pipeline_id' => $failed->getKey(),
            'name' => 'thumbnail',
            'status' => 'failed',
            'error' => ['code' => 'operation_failed', 'message' => 'The image could not be resized.'],
        ])->save();
        $this->getJson(self::API.'/resources/media-library/'.$id)
            ->assertJsonPath('data.media.error.message', 'The image could not be resized.');
    }

    public function test_lists_are_scoped_and_filtered_by_type(): void
    {
        $image = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->json('data.id');
        $document = $this->create(['file' => ['upload' => $this->upload('b.pdf', self::PDF, 'application/pdf')]])->json('data.id');
        Media::query()->where('uid', $document)->firstOrFail()->replicate()->forceFill(['uid' => 'med_other', 'team_id' => 2])->save();

        $ids = fn (string $query = ''): array => array_column($this->getJson(self::API.'/resources/media-library'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$image, $document], $ids());
        $this->assertSame([$document], $ids('?filter[type]=document'));
        $this->assertSame([], $ids('?filter[type]=video'));
        $this->getJson(self::API.'/resources/media-library/med_other')->assertNotFound();

        $this->deleteJson(self::API.'/resources/media-library/'.$image)->assertNoContent();
        $this->assertSame([$document], $ids());
        $this->assertSoftDeleted(Media::query()->withTrashed()->where('uid', $image)->firstOrFail());
    }

    public function test_media_fields_reference_media_of_the_mediatheque(): void
    {
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'articles';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), MediaField::make('cover')->accept('image/*')];
            }
        }]);
        $image = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')], 'name' => 'Cover'])->json('data.id');
        $document = $this->create(['file' => ['upload' => $this->upload('b.pdf', self::PDF, 'application/pdf')]])->json('data.id');

        $response = $this->postJson(self::API.'/resources/articles', ['title' => 'A', 'cover' => ['id' => $image, 'transform' => ['rotate' => 90]]])
            ->assertCreated()
            ->assertJsonPath('data.cover.id', $image)
            ->assertJsonPath('data.cover.label', 'Cover')
            ->assertJsonPath('data.cover.media.type', 'image')
            ->assertJsonPath('data.cover.transform', ['rotate' => 90]);
        $this->assertMatchesComponent($response->json('data.cover'), 'MediaReference');

        $this->postJson(self::API.'/resources/articles', ['title' => 'A', 'cover' => $document])->assertUnprocessable()->assertJsonValidationErrors('cover');
        $this->postJson(self::API.'/resources/articles', ['title' => 'A', 'cover' => 'med_missing'])->assertUnprocessable()->assertJsonValidationErrors('cover');
    }
}

/**
 * Allows files of another type than the media's.
 */
class TypeChangingRepository extends MediathequeRepository
{
    protected function replace(MediaContract $media, Source $source): MediaContract
    {
        return app(Mediatheque::class)->replaceOriginal($media, $source, allowTypeChange: true);
    }
}
