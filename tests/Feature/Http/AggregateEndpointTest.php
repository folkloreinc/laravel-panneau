<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Gate;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Author;
use TestApp\Models\Run;
use TestApp\Panneau\Resources\RunsResource;
use TestApp\Policies\RunPolicy;

/**
 * `GET /resources/{resource}/aggregate` (panneau-js decision 0084), on `EloquentRepository`.
 */
class AggregateEndpointTest extends HttpTestCase
{
    private Author $ada;

    private Author $grace;

    protected function setUp(): void
    {
        parent::setUp();

        RunPolicy::$denied = [];
        Gate::policy(Run::class, RunPolicy::class);
        Panneau::resources([RunsResource::class]);

        $this->ada = Author::query()->create(['name' => 'Ada']);
        $this->grace = Author::query()->create(['name' => 'Grace']);
        $this->createRun(['title' => 'A', 'status' => 'succeeded', 'tool' => 'import', 'author_id' => $this->ada->id, 'labels' => ['urgent'], 'cost' => 1.5, 'tokens' => 100, 'margin' => 10, 'amount' => ['amount' => 1250, 'currency' => 'CAD'], 'ran_at' => '2026-10-05 23:30:00', 'day' => '2026-10-05'], [$this->ada, $this->grace]);
        $this->createRun(['title' => 'B', 'status' => 'failed', 'tool' => 'import', 'retried' => true, 'author_id' => $this->grace->id, 'labels' => ['urgent', 'night'], 'cost' => 2.25, 'tokens' => 50, 'amount' => ['amount' => 500, 'currency' => 'CAD'], 'ran_at' => '2026-10-06 08:00:00', 'day' => '2026-10-06'], [$this->ada]);
        $this->createRun(['title' => 'C', 'status' => 'failed', 'tool' => 'export', 'author_id' => $this->ada->id, 'labels' => [], 'tokens' => 25, 'ran_at' => '2026-10-12 10:00:00']);
        $this->createRun(['title' => 'D', 'cost' => 0.25, 'tokens' => 0]);
        // Outside the scope of the resource
        $this->createRun(['title' => 'E', 'team_id' => 2, 'status' => 'succeeded', 'tokens' => 1000, 'ran_at' => '2026-10-05 10:00:00']);
    }

    protected function tearDown(): void
    {
        RunPolicy::$denied = [];

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Author>  $watchers
     */
    private function createRun(array $attributes, array $watchers = []): Run
    {
        $run = Run::query()->create($attributes + ['team_id' => 1]);
        $run->watchers()->sync(array_map(fn (Author $author): int => $author->id, $watchers));

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregate(string $query = ''): array
    {
        return $this->getJson(self::API.'/resources/runs/aggregate'.($query !== '' ? '?'.$query : ''))->assertOk()->json();
    }

    public function test_the_count_of_the_items_the_user_can_list(): void
    {
        $this->assertSame(['value' => 4], $this->aggregate());
        $this->assertSame(['value' => 4], $this->aggregate('measure=count'));
    }

    public function test_the_sum_of_a_field(): void
    {
        $this->assertSame(['value' => 175], $this->aggregate('measure=sum:tokens'));
        $this->assertEqualsWithDelta(4.0, $this->aggregate('measure=sum:cost')['value'], 0.001);
        // Money in minor units
        $this->assertSame(['value' => 1750], $this->aggregate('measure=sum:amount'));
    }

    public function test_the_filters_and_the_search_of_the_list(): void
    {
        $this->assertSame(['value' => 2], $this->aggregate('filter[status][]=failed'));
        $this->assertSame(['value' => 1], $this->aggregate('q=A'));
        $this->assertSame(['value' => 2], $this->aggregate('filter[ran_at][from]=2026-10-05&filter[ran_at][to]=2026-10-06'));
        $this->assertSame(['value' => 1], $this->aggregate('filter[author_id]='.$this->grace->id.'&measure=count'));
        // Paging and sorting don't count
        $this->assertSame(['value' => 4], $this->aggregate('page=3&perPage=1&sort=-ran_at&fields=title'));
    }

    public function test_a_series_has_a_point_per_period_from_the_first_item_to_the_last(): void
    {
        $series = $this->aggregate('group=day:ran_at');
        $this->assertSame('day', $series['period']);
        $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'], array_column($series['points'], 'date'));
        // The day of a date as the item writes it, and 0 for the days without items; items
        // without a date are left out
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 1], array_column($series['points'], 'value'));
        $this->assertSame(3, $series['total']);

        $this->assertSame(['period' => 'week', 'points' => [['date' => '2026-10-05', 'value' => 2], ['date' => '2026-10-12', 'value' => 1]], 'total' => 3], $this->aggregate('group=week:ran_at'));
        $this->assertSame(['period' => 'month', 'points' => [['date' => '2026-10-01', 'value' => 3]], 'total' => 3], $this->aggregate('group=month:ran_at'));
        // A date column, and a sum
        $this->assertSame(['period' => 'day', 'points' => [['date' => '2026-10-05', 'value' => 100], ['date' => '2026-10-06', 'value' => 50]], 'total' => 150], $this->aggregate('group=day:day&measure=sum:tokens'));
    }

    public function test_a_series_covers_the_bounds_of_the_date_range_filter_on_its_date(): void
    {
        $series = $this->aggregate('group=day:ran_at&filter[ran_at][from]=2026-10-01&filter[ran_at][to]=2026-10-07');
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07'], array_column($series['points'], 'date'));
        $this->assertSame(2, $series['total']);
        // One bound: the other is the last item
        $this->assertSame(['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'], array_column($this->aggregate('group=day:ran_at&filter[ran_at][from]=2026-10-09')['points'], 'date'));
        // Weeks from the Monday of the week of the bound
        $this->assertSame(['2026-09-28', '2026-10-05', '2026-10-12'], array_column($this->aggregate('group=week:ran_at&filter[ran_at][from]=2026-10-01&filter[ran_at][to]=2026-10-14')['points'], 'date'));
        // No items, no bounds: no points
        $this->assertSame(['period' => 'day', 'points' => [], 'total' => 0], $this->aggregate('group=day:ran_at&q=nothing'));
    }

    public function test_a_series_has_at_most_366_points_the_latest(): void
    {
        $points = $this->aggregate('group=day:ran_at&filter[ran_at][from]=2025-01-01&filter[ran_at][to]=2026-12-31')['points'];

        $this->assertCount(366, $points);
        $this->assertSame('2025-12-31', $points[0]['date']);
        $this->assertSame('2026-12-31', $points[365]['date']);
    }

    public function test_a_breakdown_of_a_select_has_the_labels_and_tones_of_its_options(): void
    {
        $this->assertSame([
            'items' => [
                ['key' => 'failed', 'label' => 'Failed', 'value' => 2, 'tone' => 'danger'],
                // Equal parts in the order of the options
                ['key' => 'succeeded', 'label' => 'Succeeded', 'value' => 1, 'tone' => 'success'],
                ['key' => null, 'label' => 'None', 'value' => 1],
            ],
            'total' => 4,
        ], $this->aggregate('group=status'));
        $this->assertSame([
            'items' => [
                ['key' => 'succeeded', 'label' => 'Succeeded', 'value' => 100, 'tone' => 'success'],
                ['key' => 'failed', 'label' => 'Failed', 'value' => 75, 'tone' => 'danger'],
                ['key' => null, 'label' => 'None', 'value' => 0],
            ],
            'total' => 175,
        ], $this->aggregate('group=status&measure=sum:tokens'));
    }

    public function test_a_breakdown_of_values_and_booleans(): void
    {
        $this->assertSame([
            'items' => [
                ['key' => 'import', 'label' => 'import', 'value' => 2],
                // Equal parts in the order of their labels
                ['key' => 'export', 'label' => 'export', 'value' => 1],
                ['key' => null, 'label' => 'None', 'value' => 1],
            ],
            'total' => 4,
        ], $this->aggregate('group=tool'));
        $this->assertSame([
            'items' => [['key' => false, 'label' => 'No', 'value' => 3], ['key' => true, 'label' => 'Yes', 'value' => 1]],
            'total' => 4,
        ], $this->aggregate('group=retried'));
    }

    public function test_a_breakdown_of_a_relation_has_the_ids_and_titles_of_the_related_items(): void
    {
        $this->assertSame([
            'items' => [
                ['key' => (string) $this->ada->id, 'label' => 'Ada', 'value' => 2],
                ['key' => (string) $this->grace->id, 'label' => 'Grace', 'value' => 1],
                ['key' => null, 'label' => 'None', 'value' => 1],
            ],
            'total' => 4,
        ], $this->aggregate('group=author'));
    }

    public function test_an_item_with_several_values_counts_in_each_part_and_once_in_the_total(): void
    {
        $this->assertSame([
            'items' => [
                ['key' => (string) $this->ada->id, 'label' => 'Ada', 'value' => 2],
                ['key' => null, 'label' => 'None', 'value' => 2],
                ['key' => (string) $this->grace->id, 'label' => 'Grace', 'value' => 1],
            ],
            'total' => 4,
        ], $this->aggregate('group=watchers'));
        $this->assertSame([
            'items' => [
                ['key' => 'urgent', 'label' => 'Urgent', 'value' => 2],
                ['key' => null, 'label' => 'None', 'value' => 2],
                ['key' => 'night', 'label' => 'Night', 'value' => 1],
            ],
            'total' => 4,
        ], $this->aggregate('group=labels'));
    }

    public function test_a_breakdown_has_at_most_10_parts_and_the_rest_in_other(): void
    {
        foreach (range(1, 10) as $index) {
            $this->createRun(['title' => 'Tool '.$index, 'tool' => sprintf('tool-%02d', $index)]);
        }

        $breakdown = $this->aggregate('group=tool');
        $this->assertCount(10, $breakdown['items']);
        // Equal parts in the order of their labels: None before tool-01
        $this->assertSame(['import', 'export', null, 'tool-01'], array_slice(array_column($breakdown['items'], 'key'), 0, 4));
        $this->assertSame(14, $breakdown['total']);
        // The last parts: tool-08, tool-09 and tool-10
        $this->assertSame(3, $breakdown['other']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'an unknown measure' => ['measure=average', 'measure', 'The measure must be `count` or `sum:{field}`.'],
            'a sum of a field that is not declared' => ['measure=sum:title', 'measure', 'The field `title` can\'t be summed.'],
            'a sum of a field the user can\'t see' => ['measure=sum:margin', 'measure', 'The field `margin` can\'t be summed.'],
            'a group by a field that is not declared' => ['group=title', 'group', 'The items can\'t be grouped by the field `title`.'],
            'a period of a field that is not a date' => ['group=day:title', 'group', 'The field `title` isn\'t a date that can be grouped.'],
            'a period of a year' => ['group=year:ran_at', 'group', 'The group must be `{day|week|month}:{date field}` or a field.'],
            'an empty group' => ['group=', 'group', 'The group must be `{day|week|month}:{date field}` or a field.'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_what_the_aggregates_do_not_declare_is_a_422(string $query, string $key, string $message): void
    {
        $this->getJson(self::API.'/resources/runs/aggregate?'.$query)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', Problem::VALIDATION)
            ->assertJsonPath('errors.'.$key.'.0', $message);
    }

    public function test_a_resource_without_aggregates_is_a_404(): void
    {
        $this->getJson(self::API.'/resources/posts/aggregate')
            ->assertNotFound()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->getJson(self::API.'/resources/nope/aggregate')->assertNotFound();
    }

    public function test_a_resource_the_user_can_not_list_is_a_403(): void
    {
        RunPolicy::$denied = ['viewAny'];

        $this->getJson(self::API.'/resources/runs/aggregate')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_the_item_route_is_not_the_aggregate_route(): void
    {
        $run = Run::query()->where('title', 'A')->firstOrFail();

        $this->getJson(self::API.'/resources/runs/'.$run->id)->assertOk()->assertJsonPath('data.title', 'A');
    }

    public function test_guests_get_a_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::API.'/resources/runs/aggregate')->assertUnauthorized();
    }
}
