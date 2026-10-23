<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Panneau\Contracts\UploadStorage;
use Panneau\Http\Middleware\RenderProblems;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * Projects that mount only parts of the API, under their own group.
 */
class PartialRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::allowWithoutPolicies();
        Panneau::resources([TagsResource::class, AuthorsResource::class]);
        $this->actingAs(new GenericUser(['id' => 1]));
    }

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
        $app['config']->set('panneau.uploads', ['enabled' => true, 'disk' => 'uploads']);
    }

    public function test_the_api_without_the_page_of_the_app(): void
    {
        Panneau::routes(app: false);
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertTrue(Route::has('panneau.api.definition'));
        $this->assertTrue(Route::has('panneau.api.resources.store'));
        $this->assertFalse(Route::has('panneau.app'));
        $this->get('/panneau')->assertNotFound();
        $this->get('/panneau/pages')->assertNotFound();
    }

    public function test_the_upload_routes_in_the_callers_group(): void
    {
        Route::prefix('workspaces/{workspace}')->middleware('web')->group(fn () => Panneau::uploadRoutes());
        $this->app['router']->getRoutes()->refreshNameLookups();

        $sign = Route::getRoutes()->getByName('panneau.api.uploads.sign');
        $complete = Route::getRoutes()->getByName('panneau.api.uploads.complete');
        $this->assertSame('workspaces/{workspace}/uploads/sign', $sign?->uri());
        $this->assertSame('workspaces/{workspace}/uploads/{upload}/complete', $complete?->uri());
        $this->assertSame(['web', RenderProblems::class], $sign->middleware());
        $this->assertFalse(Route::has('panneau.api.definition'));
        $this->assertFalse(Route::has('panneau.api.resources.store'));
        $this->assertFalse(Route::has('panneau.app'));

        $storage = new FakeUploadStorage($this->app->make(S3UploadStorage::class));
        $this->app->instance(UploadStorage::class, $storage);
        $this->app->forgetInstance(Uploads::class);
        $signed = $this->postJson('/workspaces/acme/uploads/sign', ['method' => 'POST', 'key' => 'photo.jpg'])->assertOk()->json();
        $storage->files[$signed['key']] = ['size' => 800, 'mime' => 'image/jpeg'];
        $this->postJson('/workspaces/acme/uploads/'.$signed['upload'].'/complete')
            ->assertOk()
            ->assertJsonPath('data.id', $signed['upload']);
        $this->postJson('/workspaces/acme/uploads/upl_unknown/complete')
            ->assertNotFound()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_no_upload_routes_without_uploads(): void
    {
        $this->app['config']->set('panneau.uploads.enabled', false);
        $this->app->forgetInstance(Uploads::class);
        Panneau::uploadRoutes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.uploads.sign'));
    }

    public function test_some_routes_of_some_resources(): void
    {
        Route::prefix('workspaces/{workspace}')->middleware('web')->group(fn () => Panneau::resourceRoutes(['tags'], only: ['store']));
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertTrue(Route::has('panneau.api.resources.store'));
        $this->assertFalse(Route::has('panneau.api.resources.index'));
        $this->assertFalse(Route::has('panneau.api.resources.show'));
        $this->assertFalse(Route::has('panneau.api.resources.actions'));

        $this->postJson('/workspaces/acme/resources/tags', ['name' => 'News', 'slug' => 'news'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'News');
        $this->postJson('/workspaces/acme/resources/tags', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->postJson('/workspaces/acme/resources/authors', ['name' => 'Ada'])->assertNotFound();
    }

    public function test_every_route_of_every_resource(): void
    {
        Route::prefix('admin')->group(fn () => Panneau::resourceRoutes());
        $this->app['router']->getRoutes()->refreshNameLookups();

        foreach (['index', 'store', 'show', 'update', 'destroy', 'actions', 'fields.check'] as $name) {
            $this->assertTrue(Route::has('panneau.api.resources.'.$name), $name);
        }
        $this->getJson('/admin/resources/authors')->assertOk();
    }

    public function test_unknown_resource_routes_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Panneau::resourceRoutes(only: ['store', 'publish']);
    }

    public function test_an_empty_list_of_resources_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Panneau::resourceRoutes([]);
    }
}
