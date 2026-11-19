<?php

namespace Panneau\Tests\Feature\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Panneau\Aggregates\AggregateResult;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Aggregates\Measure;
use Panneau\Exceptions\TooManyItemsToAggregateException;
use Panneau\Query\ResourceQuery;
use Panneau\Repositories\EloquentRepository;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Author;
use TestApp\Models\Run;
use TestApp\Models\TaxedRun;

/**
 * `EloquentRepository::aggregate()` (panneau-js decision 0084): a `GROUP BY` in SQL, and the same
 * answers in PHP, where SQL can't group (values of several items, accessors, other databases).
 */
class EloquentAggregatesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../fixture/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $ada = Author::query()->create(['name' => 'Ada']);
        $grace = Author::query()->create(['name' => 'Grace']);
        foreach ([
            ['status' => 'succeeded', 'tool' => 'import', 'author_id' => $ada->id, 'tokens' => 100, 'amount' => ['amount' => 1250, 'currency' => 'CAD'], 'ran_at' => '2026-10-04 23:59:59', 'retried' => true],
            ['status' => 'failed', 'tool' => 'import', 'author_id' => $grace->id, 'tokens' => 50, 'amount' => ['amount' => 500, 'currency' => 'CAD'], 'ran_at' => '2026-10-05 00:00:00'],
            ['status' => 'failed', 'tool' => '', 'author_id' => $ada->id, 'tokens' => 25, 'ran_at' => '2026-11-01 12:00:00'],
            ['status' => null, 'tokens' => null, 'ran_at' => null],
        ] as $index => $attributes) {
            Run::query()->create($attributes + ['team_id' => 1, 'title' => 'Run '.$index, 'day' => isset($attributes['ran_at']) ? substr($attributes['ran_at'], 0, 10) : null]);
        }
    }

    /**
     * @return EloquentRepository<Run>
     */
    private static function inSql(): EloquentRepository
    {
        return new EloquentRepository(Run::class);
    }

    /**
     * A repository that aggregates everything in PHP, as on a database without a truncation of
     * dates, or for columns it can't group.
     *
     * @return EloquentRepository<Run>
     */
    private static function inPhp(): EloquentRepository
    {
        return new class(Run::class) extends EloquentRepository
        {
            protected function aggregateColumn(Model $model, string $field): ?string
            {
                return null;
            }

            protected function truncatedDate(Builder $builder, string $column, Interval $interval): ?string
            {
                return null;
            }
        };
    }

    /**
     * @return array<string, array{Measure, Group|null, AggregateResult}>
     */
    public static function aggregates(): array
    {
        return [
            'a count' => [Measure::count(), null, new AggregateResult(4)],
            'a sum' => [Measure::sum('tokens'), null, new AggregateResult(175)],
            'a sum of money, in minor units' => [Measure::sum('amount'), null, new AggregateResult(1750)],
            'per day' => [Measure::count(), Group::period('day', 'ran_at'), new AggregateResult(3, ['2026-10-04' => 1, '2026-10-05' => 1, '2026-11-01' => 1])],
            // 2026-10-04 is a Sunday: the week of 2026-09-28
            'per week' => [Measure::count(), Group::period('week', 'ran_at'), new AggregateResult(3, ['2026-09-28' => 1, '2026-10-05' => 1, '2026-10-26' => 1])],
            'per month' => [Measure::sum('tokens'), Group::period('month', 'ran_at'), new AggregateResult(175, ['2026-10-01' => 150, '2026-11-01' => 25])],
            'per day of a date' => [Measure::count(), Group::period('day', 'day'), new AggregateResult(3, ['2026-10-04' => 1, '2026-10-05' => 1, '2026-11-01' => 1])],
            'by a value' => [Measure::count(), Group::field('status'), new AggregateResult(4, [], [['key' => 'failed', 'value' => 2], ['key' => 'succeeded', 'value' => 1], ['key' => null, 'value' => 1]])],
            // An empty string is no value
            'by a value, with sums' => [Measure::sum('tokens'), Group::field('tool'), new AggregateResult(175, [], [['key' => null, 'value' => 25], ['key' => 'import', 'value' => 150]])],
            'by a boolean' => [Measure::count(), Group::field('retried'), new AggregateResult(4, [], [['key' => false, 'value' => 3], ['key' => true, 'value' => 1]])],
            'by a relation' => [Measure::count(), Group::field('author'), new AggregateResult(4, [], [['key' => null, 'value' => 1], ['key' => '1', 'value' => 2], ['key' => '2', 'value' => 1]])],
        ];
    }

    #[DataProvider('aggregates')]
    public function test_sql_and_php_give_the_same_answers(Measure $measure, ?Group $group, AggregateResult $expected): void
    {
        $query = new ResourceQuery(scope: ['team_id' => 1]);

        foreach (['SQL' => self::inSql(), 'PHP' => self::inPhp()] as $where => $repository) {
            $result = $repository->aggregate($query, $measure, $group);
            $this->assertSame($expected->total, $result->total, $where);
            $periods = $result->periods;
            ksort($periods);
            $this->assertSame($expected->periods, $periods, $where);
            $this->assertEqualsCanonicalizing($expected->parts, $this->relationKeys($result->parts), $where);
        }
    }

    public function test_the_query_filters_the_items(): void
    {
        $query = new ResourceQuery(scope: ['team_id' => 1], filters: ['status' => 'failed', 'ran_at' => ['from' => '2026-10-05']]);

        $this->assertSame(2, self::inSql()->aggregate($query, Measure::count())->total);
        $this->assertSame(['2026-10-05' => 1, '2026-11-01' => 1], self::inSql()->aggregate($query, Measure::count(), Group::period('day', 'ran_at'))->periods);
        $this->assertSame(0, self::inSql()->aggregate(new ResourceQuery(scope: ['team_id' => 2]), Measure::count())->total);
        // A search without search fields finds nothing
        $this->assertSame(0, self::inSql()->aggregate(new ResourceQuery(scope: ['team_id' => 1], search: 'Run'), Measure::count())->total);
        $this->assertSame(4, self::inSql()->aggregate(new ResourceQuery(scope: ['team_id' => 1], search: 'Run', searchFields: ['title']), Measure::count())->total);
    }

    /**
     * @return array<string, array{class-string<Run>, Measure, Group|null, bool}>
     */
    public static function inMemory(): array
    {
        return [
            'a count' => [Run::class, Measure::count(), null, false],
            'a sum of a column' => [Run::class, Measure::sum('tokens'), null, false],
            'a sum of money' => [Run::class, Measure::sum('amount'), null, false],
            'a sum of an accessor' => [TaxedRun::class, Measure::sum('taxed_tokens'), null, true],
            'per day of a datetime' => [Run::class, Measure::count(), Group::period('day', 'ran_at'), false],
            'by a value' => [Run::class, Measure::count(), Group::field('status'), false],
            'by a boolean' => [Run::class, Measure::count(), Group::field('retried'), false],
            'by a BelongsTo relation' => [Run::class, Measure::count(), Group::field('author'), false],
            'by a BelongsToMany relation' => [Run::class, Measure::count(), Group::field('watchers'), true],
            'by a column cast to an array' => [Run::class, Measure::count(), Group::field('labels'), true],
            'by the values of a datetime' => [Run::class, Measure::count(), Group::field('ran_at'), true],
            'by a value, with a sum of an accessor' => [TaxedRun::class, Measure::sum('taxed_tokens'), Group::field('status'), true],
        ];
    }

    /**
     * @param  class-string<Run>  $model
     */
    #[DataProvider('inMemory')]
    public function test_it_tells_which_aggregates_it_computes_in_memory(string $model, Measure $measure, ?Group $group, bool $expected): void
    {
        $this->assertSame($expected, (new EloquentRepository($model))->aggregatesInMemory($measure, $group));
    }

    public function test_dates_are_computed_in_memory_on_databases_without_a_truncation_of_dates(): void
    {
        $repository = new class(Run::class) extends EloquentRepository
        {
            protected function truncatedDate(Builder $builder, string $column, Interval $interval): ?string
            {
                return null;
            }
        };

        $this->assertTrue($repository->aggregatesInMemory(Measure::count(), Group::period('week', 'ran_at')));
        $this->assertFalse($repository->aggregatesInMemory(Measure::count(), Group::field('status')));
    }

    /**
     * The queries of an aggregate, in SQL.
     *
     * @return list<string>
     */
    private function queriesOf(callable $aggregate): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $aggregate();
        DB::disableQueryLog();

        return array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
    }

    public function test_the_rows_read_in_memory_have_only_the_columns_that_the_fields_read(): void
    {
        $query = new ResourceQuery(scope: ['team_id' => 1]);

        $queries = $this->queriesOf(fn () => self::inSql()->aggregate($query, Measure::count(), Group::field('labels')));
        $this->assertStringStartsWith('select "runs"."id", "runs"."labels" from "runs"', $queries[1]);

        // The amount and currency of money, and the key that the related items are loaded by
        $queries = $this->queriesOf(fn () => self::inSql()->aggregate($query, Measure::sum('amount'), Group::field('watchers')));
        $this->assertStringStartsWith('select "runs"."id", "runs"."amount_cents", "runs"."currency" from "runs"', $queries[1]);
        $this->assertStringContainsString('"author_run"', $queries[2]);

        // The columns of the dates of other databases
        $queries = $this->queriesOf(fn () => self::inPhp()->aggregate($query, Measure::sum('tokens'), Group::period('day', 'ran_at')));
        $this->assertStringStartsWith('select "runs"."id", "runs"."tokens", "runs"."ran_at" from "runs"', $queries[1]);

        // An accessor can read any column
        $queries = $this->queriesOf(fn () => (new EloquentRepository(TaxedRun::class))->aggregate($query, Measure::sum('taxed_tokens')));
        $this->assertStringStartsWith('select * from "runs"', $queries[1]);
    }

    public function test_an_accessor_is_summed_in_memory(): void
    {
        $this->assertSame(193, (new EloquentRepository(TaxedRun::class))->aggregate(new ResourceQuery(scope: ['team_id' => 1]), Measure::sum('taxed_tokens'))->total);
    }

    public function test_aggregates_in_memory_read_at_most_max_in_memory_items(): void
    {
        $query = new ResourceQuery(scope: ['team_id' => 1]);
        $this->app['config']->set('panneau.aggregates.max_in_memory', 4);
        $this->assertSame(4, self::inSql()->aggregate($query, Measure::count(), Group::field('labels'))->total);

        $this->app['config']->set('panneau.aggregates.max_in_memory', 3);
        // In SQL, any number of items
        $this->assertSame(4, self::inSql()->aggregate($query, Measure::count(), Group::field('status'))->total);
        // Within the filters of the query
        $this->assertSame(2, self::inSql()->aggregate(new ResourceQuery(scope: ['team_id' => 1], filters: ['status' => 'failed']), Measure::count(), Group::field('labels'))->total);

        $queries = [];
        try {
            $queries = $this->queriesOf(fn () => self::inSql()->aggregate($query, Measure::count(), Group::field('labels')));
            $this->fail('No exception.');
        } catch (TooManyItemsToAggregateException $exception) {
            $this->assertSame(4, $exception->count);
            $this->assertSame(3, $exception->max);
        }
        // Counted, not read
        $this->assertSame([], array_filter(DB::getQueryLog(), fn (array $logged): bool => str_starts_with($logged['query'], 'select "runs"."id"')));
        $this->assertSame([], $queries);

        $this->app['config']->set('panneau.aggregates.max_in_memory', null);
        $this->assertSame(4, self::inSql()->aggregate($query, Measure::count(), Group::field('labels'))->total);
    }

    /**
     * The keys of a relation as strings, whichever way they were read.
     *
     * @param  list<array{key: string|int|float|bool|null, value: int|float}>  $parts
     * @return list<array{key: string|int|float|bool|null, value: int|float}>
     */
    private function relationKeys(array $parts): array
    {
        return array_map(fn (array $part): array => ['key' => is_int($part['key']) ? (string) $part['key'] : $part['key'], 'value' => $part['value']], $parts);
    }
}
