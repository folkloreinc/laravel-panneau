<?php

namespace Panneau\Tests\Feature\Http;

use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\ScoutServiceProvider;
use Panneau\Query\ResourceQuery;
use Panneau\Repositories\EloquentRepository;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Note;
use TestApp\Panneau\Resources\NotesFeedResource;
use TestApp\Panneau\Resources\NotesResource;

/**
 * Models that use Laravel Scout are searched through it, with the engines that search in the
 * database: `collection` and `database`.
 */
class ScoutSearchTest extends HttpTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ScoutServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::allowWithoutPolicies();
        Panneau::resources([NotesResource::class]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function engines(): array
    {
        return ['collection' => ['collection'], 'database' => ['database']];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createNote(array $attributes): Note
    {
        return Note::query()->create($attributes + ['team_id' => 1, 'body' => null]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<string>
     */
    private function found(string $path, array $parameters): array
    {
        $data = $this->getJson(self::API.$path.'?'.http_build_query($parameters))->assertOk()->json('data');
        $items = $path === '/search' ? array_merge([], ...array_column($data, 'items')) : $data;
        $titles = array_column($items, 'title');
        sort($titles);

        return $titles;
    }

    #[DataProvider('engines')]
    public function test_the_global_search_goes_through_scout(string $engine): void
    {
        $this->app['config']->set('scout.driver', $engine);
        $this->createNote(['title' => 'Groceries', 'body' => 'Buy summer fruits']);
        $this->createNote(['title' => 'Summer plans']);
        $this->createNote(['title' => 'Winter plans']);

        // The body is in the model's searchable data, not in the search fields of the list
        $this->assertSame(['Groceries', 'Summer plans'], $this->found('/search', ['q' => 'summer', 'resources' => 'notes']));
        $this->getJson(self::API.'/search?q=summer&resources=notes')->assertJsonPath('data.0.total', 2);
    }

    #[DataProvider('engines')]
    public function test_the_list_search_goes_through_scout(string $engine): void
    {
        $this->app['config']->set('scout.driver', $engine);
        $this->createNote(['title' => 'Groceries', 'body' => 'Buy summer fruits', 'status' => 'published']);
        $this->createNote(['title' => 'Summer plans', 'status' => 'draft']);

        $this->assertSame(['Groceries', 'Summer plans'], $this->found('/resources/notes', ['q' => 'summer']));
        // Filters still apply
        $this->assertSame(['Groceries'], $this->found('/resources/notes', ['q' => 'summer', 'filter' => ['status' => 'published']]));
        $this->getJson(self::API.'/resources/notes?q=summer&perPage=1&page=2')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(1, 'data');
    }

    #[DataProvider('engines')]
    public function test_a_list_paged_with_cursors_pages_a_scout_search_by_number(string $engine): void
    {
        $this->app['config']->set('scout.driver', $engine);
        Panneau::resources([NotesFeedResource::class]);
        foreach (['Summer 1', 'Summer 2', 'Summer 3', 'Summer 4', 'Summer 5', 'Winter'] as $title) {
            $this->createNote(['title' => $title]);
        }

        $titles = [];
        $previous = [];
        $cursor = null;
        do {
            $response = $this->getJson(self::API.'/resources/notes-feed?'.http_build_query(['q' => 'summer', 'cursor' => $cursor]))->assertOk();
            array_push($titles, ...array_column($response->json('data'), 'title'));
            $previous[] = $response->json('meta.previousCursor');
            $this->assertArrayNotHasKey('total', $response->json('meta'));
            $cursor = $response->json('meta.nextCursor');
        } while ($cursor !== null);

        sort($titles);
        $this->assertSame(['Summer 1', 'Summer 2', 'Summer 3', 'Summer 4', 'Summer 5'], $titles);
        // Pages 1 and 2 go back to the first page, without a cursor
        $this->assertNull($previous[0]);
        $this->assertNull($previous[1]);
        $this->assertCount(2, $this->getJson(self::API.'/resources/notes-feed?'.http_build_query(['q' => 'summer', 'cursor' => $previous[2]]))->json('data'));
    }

    #[DataProvider('engines')]
    public function test_the_scope_applies(string $engine): void
    {
        $this->app['config']->set('scout.driver', $engine);
        $this->createNote(['title' => 'Our summer']);
        $this->createNote(['title' => 'Their summer', 'team_id' => 2]);

        $this->assertSame(['Our summer'], $this->found('/search', ['q' => 'summer']));
        $this->getJson(self::API.'/search?q=summer')->assertJsonPath('data.0.total', 1);
        $this->assertSame(['Our summer'], $this->found('/resources/notes', ['q' => 'summer']));
    }

    public function test_the_scope_is_given_to_the_engine(): void
    {
        $repository = new class(Note::class) extends EloquentRepository
        {
            /**
             * @return ScoutBuilder<Note>
             */
            public function search(ResourceQuery $query): ScoutBuilder
            {
                return $this->scoutSearch($query);
            }
        };

        $search = $repository->search(new ResourceQuery(search: 'summer', scope: ['team_id' => 1, 'status' => ['draft', 'published']]));

        // For engines with their own index, which apply the query callback after paginating
        $this->assertSame([['field' => 'team_id', 'operator' => '=', 'value' => 1]], $search->wheres);
        $this->assertSame(['status' => ['draft', 'published']], $search->whereIns);
        $this->assertNotNull($search->queryCallback);
    }

    #[DataProvider('engines')]
    public function test_chunks_go_through_scout(string $engine): void
    {
        $this->app['config']->set('scout.driver', $engine);
        $this->createNote(['title' => 'Groceries', 'body' => 'Buy summer fruits']);
        $this->createNote(['title' => 'Summer plans']);
        $this->createNote(['title' => 'Their summer', 'team_id' => 2]);
        $this->createNote(['title' => 'Winter plans']);
        $excluded = $this->createNote(['title' => 'Summer again']);

        $titles = [];
        EloquentRepository::for(Note::class)->chunk(
            (new ResourceQuery(search: 'summer', scope: ['team_id' => 1]))->withExcept([(string) $excluded->id]),
            1,
            function ($items) use (&$titles): void {
                array_push($titles, ...$items->pluck('title')->all());
            },
        );

        $this->assertSame(['Groceries', 'Summer plans'], $titles);
    }

    public function test_models_without_scout_keep_like_queries(): void
    {
        $this->createPost(['title' => 'Summer', 'body' => 'Long days']);

        $this->assertSame(['Summer'], $this->found('/search', ['q' => 'summer', 'resources' => 'posts']));
    }
}
