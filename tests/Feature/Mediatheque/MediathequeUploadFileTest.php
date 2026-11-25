<?php

namespace Panneau\Tests\Feature\Mediatheque;

use Folklore\Image\ImageServiceProvider;
use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Events\MediaCreated;
use Folklore\Mediatheque\Models\Media;
use Folklore\Mediatheque\ServiceProvider as MediathequeServiceProvider;
use Folklore\Mediatheque\Support\Models;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Agents\McpTestCase;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use TestApp\Panneau\Resources\MediaLibraryResource;

/**
 * `upload_file` on a library of laravel-mediatheque 2: the file an agent sends is ingested as
 * the one of the admin is. Skipped when the mediatheque is not installed.
 */
#[WithMigration]
class MediathequeUploadFileTest extends McpTestCase
{
    use MakesUploads;

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
        parent::defineEnvironment($app);
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
        Storage::fake('media');
        Bus::fake();
        Panneau::resources([MediaLibraryResource::class]);
        Panneau::allowWithoutPolicies();
    }

    public function test_the_file_of_an_agent_is_ingested_by_the_mediatheque(): void
    {
        Event::fake([MediaCreated::class]);
        $defaultGuard = $this->app['config']->get('auth.defaults.guard');
        $token = $this->createUser()->createToken('assistant', ['mcp:use', 'panneau:write'])->plainTextToken;

        $upload = $this->rpc($token, 'tools/call', ['name' => 'upload_file', 'arguments' => ['name' => 'terms.pdf']])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->json('result.structuredContent');
        $this->assertSame('media-library', $upload['resource']);
        $this->assertSame(0, Media::query()->count());

        // A request of its own, with no token
        $this->app['auth']->forgetGuards();
        $this->app['config']->set('auth.defaults.guard', $defaultGuard);
        $media = $this->call('PUT', $upload['url'], [], [], [], [], self::PDF)->assertCreated()->json();

        $model = Media::query()->sole();
        $this->assertStringStartsWith('med_', $media['id']);
        $this->assertSame('document', $media['media']['type']);
        $this->assertSame('application/pdf', $media['media']['mime']);
        $this->assertSame('terms.pdf', $media['file']['name']);
        // The scope of the library
        $this->assertSame(1, (int) $model->getAttribute('team_id'));
        $this->assertNotSame([], Storage::disk('media')->allFiles());
        Event::assertDispatched(MediaCreated::class);

        // A type the library doesn't take
        $this->app['auth']->forgetGuards();
        $this->app['config']->set('auth.defaults.guard', $defaultGuard);
        $response = $this->call('PUT', $upload['url'], [], [], [], [], 'plain text')->assertStatus(422);
        $this->assertSame(['file'], array_keys($response->json('errors')));
        $this->assertSame(1, Media::query()->count());
    }
}
