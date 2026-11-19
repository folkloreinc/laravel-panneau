<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Panneau\Fields\Link;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Fields\RichText;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Asset;
use TestApp\Models\Author;
use TestApp\Models\Page;
use TestApp\Models\Post;
use TestApp\Models\Tag;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Panneau\Resources\PagesResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * A list loads the values of its items in a number of queries that doesn't grow with the number
 * of items: the related items of its relations, the pages of its links and its media are found
 * once for the page of items, and the fields it didn't ask for aren't read.
 */
class ListQueriesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Panneau::resources([PagesResource::class, ImagesResource::class, ListedPostsResource::class]);
    }

    /**
     * Posts with an author, two tags, a link to a page and a cover.
     */
    private function createPosts(int $count): void
    {
        $offset = Post::query()->count();
        foreach (range($offset + 1, $offset + $count) as $index) {
            $author = Author::query()->create(['name' => 'Author '.$index]);
            $page = Page::query()->create(['team_id' => 1, 'title' => ['en' => 'Page '.$index, 'fr' => 'Page '.$index]]);
            $cover = Asset::query()->create([
                'team_id' => 1,
                'type' => 'image',
                'name' => 'Cover '.$index,
                'file' => ['disk' => 'public', 'path' => 'covers/'.$index.'.png', 'name' => $index.'.png', 'mime' => 'image/png', 'size' => 1000],
            ]);
            $post = $this->createPost([
                'title' => 'Post '.$index,
                'author_id' => $author->id,
                'link' => ['type' => 'page', 'page' => ['id' => (string) $page->id]],
                'details' => ['cover' => ['id' => (string) $cover->id]],
            ]);
            $post->tags()->attach([
                Tag::query()->create(['slug' => 'tag-'.$index.'-a', 'name' => 'Tag A'])->id,
                Tag::query()->create(['slug' => 'tag-'.$index.'-b', 'name' => 'Tag B'])->id,
            ]);
        }
    }

    /**
     * The queries of a request to the list.
     *
     * @return list<string>
     */
    private function listQueries(string $query = ''): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(self::API.'/resources/listed-posts'.$query)->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    public function test_a_list_has_the_references_of_its_relations_links_and_media(): void
    {
        $this->createPosts(2);

        $item = $this->getJson(self::API.'/resources/listed-posts?sort=title')->assertOk()->json('data.0');

        $this->assertSame('Post 1', $item['title']);
        $this->assertSame(['id' => (string) Author::query()->where('name', 'Author 1')->value('id'), 'label' => 'Author 1'], $item['author']);
        $this->assertSame([['id' => 'tag-1-a', 'label' => 'Tag A'], ['id' => 'tag-1-b', 'label' => 'Tag B']], $item['tags']);
        $this->assertSame('pages', $item['link']['page']['resource']);
        $this->assertSame('Page 1', $item['link']['page']['label']);
        $this->assertSame('https://example.com/pages/'.$item['link']['page']['id'], $item['link']['url']);
        $this->assertSame('Cover 1', $item['cover']['label']);
        $this->assertSame('image', $item['cover']['media']['type']);
    }

    public function test_the_queries_of_a_list_dont_grow_with_its_items(): void
    {
        $this->createPosts(5);
        $few = $this->listQueries();

        $this->createPosts(15);
        $many = $this->listQueries();

        $this->assertCount(count($few), $many, "The list of 20 items made more queries than the list of 5:\n".implode("\n", $many));
        // The page of posts, the authors, the tags, the pages of the links and the covers (a page
        // and its count for each of them, through their repository)
        $this->assertLessThanOrEqual(8, count($many), implode("\n", $many));
    }

    public function test_a_list_only_reads_the_fields_it_asks_for(): void
    {
        $this->createPosts(3);

        $queries = $this->listQueries('?fields=title');

        // The page of posts, and the covers, since the cover is the image of an item: neither
        // the authors, the tags nor the pages of the links
        $this->assertCount(4, $queries, implode("\n", $queries));
        $this->assertSame([], array_values(array_filter($queries, fn (string $query): bool => preg_match('/"(authors|tags|pages)"/', $query) === 1)));
        $data = $this->getJson(self::API.'/resources/listed-posts?fields=title')->assertOk()->json('data.0');
        $this->assertSame(['id', 'version', 'abilities', 'title', 'cover'], array_keys($data));
    }

    public function test_an_item_asked_for_some_fields_has_its_title_and_image(): void
    {
        $this->createPosts(1);

        $data = $this->getJson(self::API.'/resources/listed-posts?fields=author')->assertOk()->json('data.0');

        $this->assertSame(['id', 'version', 'abilities', 'title', 'author', 'cover'], array_keys($data));
        $this->assertSame('Cover 1', $data['cover']['label']);
    }

    public function test_a_list_without_fields_has_every_field(): void
    {
        $this->createPosts(1);

        $data = $this->getJson(self::API.'/resources/listed-posts')->assertOk()->json('data.0');

        $this->assertSame(['id', 'version', 'abilities', 'title', 'body', 'author', 'tags', 'link', 'cover'], array_keys($data));
    }
}

/**
 * Posts with a relation, a link to pages and a media column.
 */
class ListedPostsResource extends Resource
{
    public function model(): string
    {
        return Post::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title'),
            RichText::make('body'),
            Relation::make('author')->resource(AuthorsResource::class),
            Relation::make('tags')->resource(TagsResource::class)->multiple(),
            Link::make('link')->pages(PagesResource::class)
                ->resolvePageUrlUsing(fn (object $page, string $resource): string => 'https://example.com/'.$resource.'/'.$page->getKey()),
            Media::make('cover')->resource(ImagesResource::class)
                ->resolveUsing(fn (Post $post): mixed => $post->details['cover'] ?? null),
        ];
    }

    public function itemImage(): string
    {
        return 'cover';
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['title', 'author', 'tags', 'link', 'cover'])->sorts(['title']);
    }
}
