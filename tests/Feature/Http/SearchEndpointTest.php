<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Panneau\Http\Problem;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Author;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Policies\PostPolicy;
use TestApp\Repositories\Adapters\ArrayRepository;

class SearchEndpointTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // After posts, authors and tags: people are authors with a search on their name
        Panneau::resources([new class extends AuthorsResource
        {
            public function id(): string
            {
                return 'people';
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->search(['name']);
            }
        }]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function search(array $parameters): array
    {
        return $this->getJson(self::API.'/search?'.http_build_query($parameters))->assertOk()->json('data');
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, list<mixed>>
     */
    private static function titles(array $data, string $field = 'title'): array
    {
        $titles = [];
        foreach ($data as $group) {
            $titles[$group['resource']] = array_column($group['items'], $group['resource'] === 'people' ? 'name' : $field);
        }

        return $titles;
    }

    public function test_results_are_grouped_by_resource_in_the_order_of_the_definition(): void
    {
        $this->createPost(['title' => 'Summer in Montreal', 'published_at' => '2026-07-01 10:00:00']);
        $this->createPost(['title' => 'Winter']);
        $this->createPost(['title' => 'Late summer', 'published_at' => '2026-09-01 10:00:00']);
        Author::query()->create(['name' => 'Summer Ada']);

        $data = $this->search(['q' => 'summer', 'resources' => 'people,posts']);

        // The default sort of the list: -published_at
        $this->assertSame(['posts' => ['Late summer', 'Summer in Montreal'], 'people' => ['Summer Ada']], self::titles($data));
        $this->assertSame([2, 1], array_column($data, 'total'));
    }

    public function test_resources_without_results_are_left_out(): void
    {
        $this->createPost(['title' => 'Summer']);

        $this->assertSame(['posts'], array_column($this->search(['q' => 'summer']), 'resource'));
        $this->assertSame([], $this->search(['q' => 'nothing']));
    }

    public function test_items_are_serialized_as_in_the_list(): void
    {
        $this->createPost(['title' => 'Locked', 'secret' => 'hidden']);

        $item = $this->search(['q' => 'lock'])[0]['items'][0];

        $this->assertSame($this->getJson(self::API.'/resources/posts?q=lock')->json('data.0'), $item);
        $this->assertSame(['view' => true, 'update' => false, 'delete' => false, 'publish-posts' => true, 'archive-posts' => false], $item['abilities']);
        $this->assertArrayNotHasKey('secret', $item);
    }

    public function test_at_most_limit_items_per_resource(): void
    {
        foreach (range(1, 7) as $index) {
            $this->createPost(['title' => 'Summer '.$index]);
            Author::query()->create(['name' => 'Summer '.$index]);
        }

        $data = $this->search(['q' => 'summer']);
        $this->assertSame([5, 5], array_map(fn (array $group): int => count($group['items']), $data));
        $this->assertSame([7, 7], array_column($data, 'total'));

        $data = $this->search(['q' => 'summer', 'limit' => '2']);
        $this->assertSame([2, 2], array_map(fn (array $group): int => count($group['items']), $data));
    }

    public function test_at_most_twenty_items_in_all(): void
    {
        foreach (range(1, 15) as $index) {
            $this->createPost(['title' => 'Summer '.$index]);
            Author::query()->create(['name' => 'Summer '.$index]);
        }

        $data = $this->search(['q' => 'summer', 'limit' => '20']);
        $this->assertSame(['posts', 'people'], array_column($data, 'resource'));
        $this->assertSame([15, 5], array_map(fn (array $group): int => count($group['items']), $data));
        $this->assertSame([15, 15], array_column($data, 'total'));

        // Once 20 items are found, the other resources are not searched
        foreach (range(16, 20) as $index) {
            $this->createPost(['title' => 'Summer '.$index]);
        }
        $this->assertSame(['posts'], array_column($this->search(['q' => 'summer', 'limit' => '20']), 'resource'));
    }

    public function test_only_the_resources_asked_for(): void
    {
        $this->createPost(['title' => 'Summer']);
        Author::query()->create(['name' => 'Summer Ada']);

        $this->assertSame(['people'], array_column($this->search(['q' => 'summer', 'resources' => ' people, people ,']), 'resource'));
        $this->assertSame(['posts'], array_column($this->search(['q' => 'summer', 'resources' => 'posts']), 'resource'));
    }

    public function test_the_scope_applies(): void
    {
        $this->createPost(['title' => 'Our summer']);
        $this->createPost(['title' => 'Their summer', 'team_id' => 2]);

        $data = $this->search(['q' => 'summer']);

        $this->assertSame(['posts' => ['Our summer']], self::titles($data));
        $this->assertSame(1, $data[0]['total']);
    }

    public function test_the_search_is_trimmed(): void
    {
        $this->createPost(['title' => 'Summer']);

        $this->assertSame(['posts' => ['Summer']], self::titles($this->search(['q' => '  summer '])));
    }

    public function test_resources_the_user_cannot_list_are_not_searched(): void
    {
        $this->createPost(['title' => 'Summer']);
        Author::query()->create(['name' => 'Summer Ada']);
        PostPolicy::$denied = ['viewAny'];

        $this->assertSame(['people'], array_column($this->search(['q' => 'summer']), 'resource'));
        $this->getJson(self::API.'/search?q=summer&resources=posts')
            ->assertStatus(422)
            ->assertJsonPath('errors.resources', ['These resources are not searchable: posts.']);
    }

    public function test_resources_without_a_search_are_not_searched(): void
    {
        Author::query()->create(['name' => 'Summer Ada']);

        // Authors have no search; tags have no search and no policy
        $this->getJson(self::API.'/search?q=summer&resources=authors')->assertStatus(422)->assertJsonStructure(['errors' => ['resources']]);
        $this->getJson(self::API.'/search?q=summer&resources=tags')->assertStatus(422)->assertJsonStructure(['errors' => ['resources']]);
    }

    public function test_features_take_a_resource_out_of_the_global_search(): void
    {
        Panneau::resources([new class extends AuthorsResource
        {
            public function id(): string
            {
                return 'writers';
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->search(['name']);
            }

            public function features(): array
            {
                return ['search' => false];
            }
        }]);
        Author::query()->create(['name' => 'Summer Ada']);

        $this->assertSame(['people'], array_column($this->search(['q' => 'summer']), 'resource'));
        $this->getJson(self::API.'/search?q=summer&resources=writers')->assertStatus(422);
        // The list still has its search
        $this->getJson(self::API.'/resources/writers?q=summer')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_features_put_a_resource_without_list_search_in_the_global_search(): void
    {
        Panneau::resources([new class extends AuthorsResource
        {
            public function id(): string
            {
                return 'writers';
            }

            public function features(): array
            {
                return ['search' => true];
            }
        }]);
        Author::query()->create(['name' => 'Summer Ada']);

        // Searchable, but EloquentRepository has no field to search: it finds nothing, rather
        // than every item
        $this->assertSame([], $this->search(['q' => 'summer', 'resources' => 'writers']));
    }

    public function test_a_repository_that_is_not_searchable_is_not_searched(): void
    {
        $this->app->instance(ArrayRepository::class, new ArrayRepository(['1' => (object) ['id' => '1', 'title' => 'Summer']]));
        Panneau::allowWithoutPolicies();
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'notes';
            }

            public function repository(): string
            {
                return ArrayRepository::class;
            }

            public function fields(): array
            {
                return [];
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->search(['title']);
            }

            public function features(): array
            {
                return ['search' => true];
            }
        }]);

        $this->assertSame([], $this->search(['q' => 'summer']));
        $this->getJson(self::API.'/search?q=summer&resources=notes')->assertStatus(422)->assertJsonStructure(['errors' => ['resources']]);
        $this->assertFalse(collect($this->getJson(self::API.'/definition')->json('resources'))->firstWhere('id', 'notes')['features']['search']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'no q' => ['', 'q'],
            'a short q' => ['q=a', 'q'],
            'a short q once trimmed' => ['q=%20a%20%20', 'q'],
            'a long q' => ['q='.str_repeat('a', 256), 'q'],
            'q as a list' => ['q[]=summer', 'q'],
            'a limit of 0' => ['q=summer&limit=0', 'limit'],
            'a limit above 20' => ['q=summer&limit=21', 'limit'],
            'a limit that is not an integer' => ['q=summer&limit=2.5', 'limit'],
            'an empty limit' => ['q=summer&limit=', 'limit'],
            'an unknown resource' => ['q=summer&resources=posts,unknown', 'resources'],
            'no resource' => ['q=summer&resources=', 'resources'],
            'only commas' => ['q=summer&resources=,,', 'resources'],
            'resources as a list' => ['q=summer&resources[]=posts', 'resources'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_an_invalid_parameter_is_a_422_problem(string $query, string $parameter): void
    {
        $response = $this->getJson(self::API.'/search?'.$query)
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::VALIDATION);

        $this->assertSame([$parameter], array_keys($response->json('errors')));
    }

    public function test_every_invalid_parameter_has_its_errors(): void
    {
        $this->getJson(self::API.'/search?q=a&limit=50&resources=unknown')
            ->assertStatus(422)
            ->assertExactJson([
                'type' => Problem::VALIDATION,
                'title' => 'The given data was invalid.',
                'status' => 422,
                'errors' => [
                    'q' => ['The search must have at least 2 characters.'],
                    'limit' => ['The limit must be an integer from 1 to 20.'],
                    'resources' => ['These resources are not searchable: unknown.'],
                ],
            ]);
    }

    public function test_the_longest_search(): void
    {
        $this->getJson(self::API.'/search?q='.str_repeat('é', 255))->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_guests_get_a_401_problem(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::API.'/search?q=summer')->assertStatus(401)->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_searches_are_rate_limited_for_each_user(): void
    {
        $this->app['config']->set('panneau.search.rate_limit', 2);

        $this->getJson(self::API.'/search?q=summer')->assertOk();
        $this->getJson(self::API.'/search?q=summer')->assertOk();
        $response = $this->getJson(self::API.'/search?q=summer')
            ->assertStatus(429)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertHeader('Retry-After')
            ->assertJsonPath('status', 429);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        // Invalid searches count too
        $this->getJson(self::API.'/search?q=a')->assertStatus(429);

        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace']));
        $this->getJson(self::API.'/search?q=summer')->assertOk();
    }

    public function test_no_rate_limit(): void
    {
        $this->app['config']->set('panneau.search.rate_limit', null);

        foreach (range(1, 70) as $attempt) {
            $this->getJson(self::API.'/search?q=summer')->assertOk();
        }
    }

    public function test_the_project_can_define_the_rate_limiter(): void
    {
        $this->app->make(RateLimiter::class)->for('panneau-search', fn (): Limit => Limit::perMinute(1));

        $this->getJson(self::API.'/search?q=summer')->assertOk();
        $this->getJson(self::API.'/search?q=summer')->assertStatus(429);
    }
}
