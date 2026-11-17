<?php

namespace Panneau\Tests\Feature\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Panneau\Aggregates\AggregateResult;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Aggregates\Measure;
use Panneau\Query\ResourceQuery;
use Panneau\Repositories\EloquentRepository;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Author;
use TestApp\Models\Run;

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
