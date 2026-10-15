<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\AuthorPolicy;
use TestApp\Policies\PostPolicy;

abstract class HttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        PostPolicy::$denied = [];
        $this->app['config']->set('panneau.locales', ['fr', 'en']);
        Gate::define('viewPanneau', fn ($user): bool => $user->getAuthIdentifier() !== 99);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);
        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class]);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']));
    }

    protected function tearDown(): void
    {
        PostPolicy::$denied = [];

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + ['title' => 'A post', 'team_id' => 1]);
    }
}
