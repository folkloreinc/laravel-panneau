<?php

namespace Panneau\Tests\Feature\Resources;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Repositories\EloquentRepository;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Tests\TestCase;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Tag;
use TestApp\Panneau\Actions\PublishPosts;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Repositories\Adapters\ArrayRepository;

class ResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    private function registry(): ResourceRegistry
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([PostsResource::class, AuthorsResource::class, new TagsResource]);

        return $registry;
    }

    public function test_ids_come_from_the_class_name(): void
    {
        $this->assertSame('posts', (new PostsResource)->id());
        $this->assertSame('blog-posts', (new BlogPosts)->id());
        $this->assertSame('blog post', (new BlogPosts)->singularLabel());
        $this->assertSame('blog posts', (new BlogPosts)->pluralLabel());
    }

    public function test_resources_are_found_by_id_or_class(): void
    {
        $registry = $this->registry();

        $this->assertSame(['posts', 'authors', 'tags'], array_map(fn (Resource $resource): string => $resource->id(), $registry->all()));
        $this->assertInstanceOf(PostsResource::class, $registry->find('posts'));
        $this->assertSame($registry->find('authors'), $registry->resolve(AuthorsResource::class));
        $this->assertNull($registry->find('missing'));
        $this->assertSame('tags', $registry->idOf(TagsResource::class));
        $this->assertSame('unknown', $registry->idOf('unknown'));
    }

    public function test_two_resources_cannot_share_an_id(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([PostsResource::class, new PostsResource]);

        $this->expectException(LogicException::class);

        $registry->all();
    }

    public function test_only_resources_can_be_registered(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([Post::class]);

        $this->expectException(InvalidArgumentException::class);

        $registry->all();
    }

    public function test_a_model_resource_uses_eloquent(): void
    {
        $registry = $this->registry();
        $repository = $registry->repository($registry->find('posts'));

        $this->assertInstanceOf(EloquentRepository::class, $repository);
        $this->assertSame($repository, $registry->repository($registry->find('posts')));
    }

    public function test_a_repository_resource_uses_its_repository(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([new BlogPosts]);

        $this->assertInstanceOf(ArrayRepository::class, $registry->repository($registry->find('blog-posts')));
        $this->assertFalse($registry->searchable($registry->find('blog-posts')));
        // Its list asks for cursors, but the repository can't
        $this->assertFalse($registry->cursorPaginated($registry->find('blog-posts')));
    }

    public function test_a_resource_needs_a_repository_or_a_model(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([new class extends Resource
        {
            public function fields(): array
            {
                return [];
            }
        }]);

        $this->expectException(LogicException::class);

        $registry->repository($registry->all()[0]);
    }

    public function test_actions_are_resolved(): void
    {
        $registry = $this->registry();
        $posts = $registry->find('posts');

        $this->assertInstanceOf(PublishPosts::class, $registry->action($posts, 'publish-posts'));
        $this->assertSame('preview', $registry->action($posts, 'preview')?->id());
        $this->assertNull($registry->action($posts, 'missing'));
    }

    public function test_query_options_come_from_the_list(): void
    {
        $registry = $this->registry();
        $options = $registry->queryOptions($registry->find('posts'), 20, 50);

        $this->assertSame(['status', 'featured', 'author_id', 'published_at'], array_keys($options->filters));
        $this->assertSame(['title', 'body'], $options->searchFields);
        $this->assertContains('blocks', $options->fields ?? []);
        $this->assertSame(50, $options->maxPerPage);
    }

    public function test_items_serialize_their_declared_fields_only(): void
    {
        $registry = $this->registry();
        $author = Author::query()->create(['name' => 'Ada']);
        $post = Post::query()->create([
            'title' => 'Title',
            'subtitle' => ['fr' => 'Sous-titre', 'en' => 'Subtitle'],
            'status' => 'draft',
            'secret' => 'hidden',
            'author_id' => $author->id,
            'published_at' => '2026-10-15 10:30:00',
            'seo' => ['title' => 'SEO', 'leak' => 'x'],
        ]);
        $post->tags()->attach([
            Tag::query()->create(['slug' => 'news', 'name' => 'News'])->id,
            Tag::query()->create(['slug' => 'tech', 'name' => 'Tech'])->id,
        ]);

        $item = $this->app->make(ItemSerializer::class)->serialize($registry->find('posts'), $post->refresh(), null, ['update' => true]);

        $this->assertSame((string) $post->id, $item['id']);
        $this->assertIsString($item['version']);
        $this->assertSame(['update' => true], $item['abilities']);
        $this->assertSame(['fr' => 'Sous-titre', 'en' => 'Subtitle'], $item['subtitle']);
        $this->assertSame(['id' => (string) $author->id, 'label' => 'Ada'], $item['author']);
        $this->assertSame([['id' => 'news', 'label' => 'News'], ['id' => 'tech', 'label' => 'Tech']], $item['tags']);
        $this->assertSame(['title' => 'SEO', 'description' => null], $item['seo']);
        $this->assertSame('2026-10-15T10:30:00+00:00', $item['published_at']);
        $this->assertArrayNotHasKey('secret', $item);
        $this->assertArrayNotHasKey('team_id', $item);
        $this->assertArrayNotHasKey('created_at', $item);
    }

    public function test_items_can_be_restricted_to_some_fields(): void
    {
        $registry = $this->registry();
        $post = Post::query()->create(['title' => 'Title']);

        $item = $this->app->make(ItemSerializer::class)->serialize($registry->find('posts'), $post, ['title']);

        $this->assertSame(['id', 'version', 'abilities', 'title'], array_keys($item));
        $this->assertEquals((object) [], $item['abilities']);
    }

    public function test_to_item_can_be_overridden(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register([new BlogPosts]);

        $item = $this->app->make(ItemSerializer::class)->serialize($registry->find('blog-posts'), (object) ['id' => 'a', 'title' => 'T']);

        $this->assertSame(['id' => 'a', 'version' => null, 'abilities' => $item['abilities'], 'title' => 'T!'], $item);
    }
}

class BlogPosts extends Resource
{
    public function repository(): string
    {
        return ArrayRepository::class;
    }

    public function fields(): array
    {
        return [Text::make('title')];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->cursorPagination();
    }

    public function toItem(object $item): array
    {
        return ['id' => 'ignored', 'title' => $item->title.'!'];
    }
}
