<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Panneau\Contracts\UploadStorage;
use Panneau\Support\Facade as Panneau;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;

/**
 * Panneau's routes under a prefix with its own parameter, as a multi-tenant application mounts
 * them: the controllers read their parameters by name, not by position.
 */
class PrefixedRoutesTest extends HttpTestCase
{
    private const PREFIXED_API = '/tenants/acme/panneau/api';

    private FakeUploadStorage $storage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.uploads', [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => 'panneau-test',
            'endpoint' => 'https://storage.test',
            'use_path_style_endpoint' => true,
        ]);
        $app['config']->set('panneau.uploads', [
            'enabled' => true,
            'disk' => 'uploads',
            'path' => 'panneau-uploads',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('tenants/{tenant}')->group(fn () => Panneau::routes());

        $this->storage = new FakeUploadStorage($this->app->make(S3UploadStorage::class));
        $this->app->instance(UploadStorage::class, $this->storage);
        $this->app->forgetInstance(Uploads::class);
        Gate::define('uploadPanneauFiles', fn ($user): bool => true);
    }

    public function test_routes_of_the_project_under_the_prefixed_path_keep_their_precedence(): void
    {
        $this->withoutVite();
        $this->app['router']->setRoutes(new RouteCollection);
        Route::middleware('web')->get('/{page}', fn (string $page): string => 'page '.$page);
        Route::prefix('tenants/{tenant}')->group(function (): void {
            Route::middleware('web')->get('panneau/custom', fn (): string => 'custom');
            Panneau::routes();
        });
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->get('/tenants/acme/panneau/custom')->assertOk()->assertContent('custom');
        $this->get('/tenants/acme/panneau')->assertOk()->assertSee('id="panneau-boot"', false);
        $this->get('/about')->assertOk()->assertContent('page about');
    }

    public function test_the_resource_routes(): void
    {
        $post = $this->createPost(['title' => 'First']);
        $url = self::PREFIXED_API.'/resources/posts';

        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id', (string) $post->id);
        $this->getJson($url.'/'.$post->id)->assertOk()->assertJsonPath('data.title', 'First');
        $id = $this->postJson($url, ['title' => 'Second', 'status' => 'draft', 'preview_password' => 'preview-123'])->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$post->id, ['title' => 'Renamed'])->assertOk()->assertJsonPath('data.title', 'Renamed');
        $this->postJson($url.'/actions/publish-posts', ['selection' => ['ids' => [(string) $post->id]]])
            ->assertOk()
            ->assertJsonPath('summary.succeeded', 1);
        $this->deleteJson($url.'/'.$id)->assertNoContent();
        $this->postJson($url.'/fields/slug/check', ['value' => 'free-slug', 'locale' => 'fr'])
            ->assertOk()
            ->assertJsonPath('available', true);

        $this->assertSame('published', $post->refresh()->status);
    }

    public function test_the_upload_routes(): void
    {
        $signed = $this->postJson(self::PREFIXED_API.'/uploads/sign', ['method' => 'POST', 'key' => 'video.mp4'])->assertOk()->json();
        $this->storage->files[$signed['key']] = ['size' => 800, 'mime' => 'video/mp4'];

        $this->postJson(self::PREFIXED_API.'/uploads/'.$signed['upload'].'/complete')
            ->assertOk()
            ->assertJsonPath('data.id', $signed['upload']);
    }
}
