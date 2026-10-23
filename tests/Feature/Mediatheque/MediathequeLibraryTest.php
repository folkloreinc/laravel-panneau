<?php

namespace Panneau\Tests\Feature\Mediatheque;

use Folklore\Image\ImageServiceProvider;
use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Events\MediaCreated;
use Folklore\Mediatheque\Facades\Mediatheque as MediathequeFacade;
use Folklore\Mediatheque\Jobs\ProbeMedia;
use Folklore\Mediatheque\Models\File as MediathequeFile;
use Folklore\Mediatheque\Models\Media;
use Folklore\Mediatheque\ServiceProvider as MediathequeServiceProvider;
use Folklore\Mediatheque\Support\Models;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use LogicException;
use Panneau\Fields\File;
use Panneau\Fields\Media as MediaField;
use Panneau\Fields\Text;
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
        // The mediatheque's types give the accepted files; the file can't be replaced
        $this->assertSame([
            'name' => 'file',
            'type' => 'file',
            'createOnly' => true,
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

    public function test_the_metadata_of_a_media_change_but_not_its_file(): void
    {
        $id = $this->create(['file' => ['upload' => $this->upload('a.png', self::png(), 'image/png')]])->assertCreated()->json('data.id');
        $media = Media::query()->where('uid', $id)->firstOrFail();
        $path = $media->original()?->path();

        $this->patchJson(self::API.'/resources/media-library/'.$id, [
            'name' => 'Renamed',
            'file' => ['upload' => $this->upload('b.png', self::png(), 'image/png')],
        ])->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.file.name', 'a.png');

        $this->assertSame($path, $media->refresh()->load('mediaFiles')->original()?->path());
        $this->assertSame(1, MediathequeFile::query()->count());
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
