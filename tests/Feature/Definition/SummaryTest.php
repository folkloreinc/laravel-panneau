<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Panneau\Dashboard\Summary;
use Panneau\Dashboard\Widget;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\DateTime;
use Panneau\Fields\Text;
use Panneau\Lists\Filters\DateFilter;
use Panneau\Lists\Filters\DateRangeFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Models\Run;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\RunsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\RunPolicy;

/**
 * The summary above the list of a resource, in the definition (panneau-js decision 0084).
 */
class SummaryTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        RunPolicy::$denied = [];
        Gate::policy(Run::class, RunPolicy::class);
        Panneau::resources([RunsResource::class, AuthorsResource::class, PostsResource::class, TagsResource::class]);
    }

    protected function tearDown(): void
    {
        RunPolicy::$denied = [];

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summary(string $resource, ?Authenticatable $user = null): ?array
    {
        $definition = $this->app->make(DefinitionBuilder::class)->build($user ?? new GenericUser(['id' => 1, 'name' => 'Ada']));

        return collect($definition['resources'])->firstWhere('id', $resource)['summary'] ?? null;
    }

    public function test_the_summary_of_a_resource(): void
    {
        $this->assertSame([
            'widgets' => [
                ['id' => 'failed', 'type' => 'stat', 'label' => 'Failed', 'settings' => ['resource' => 'runs', 'query' => ['filter[status][]' => 'failed']]],
                ['id' => 'per-day', 'type' => 'series', 'label' => 'Runs per day', 'settings' => ['resource' => 'runs', 'group' => 'day:ran_at']],
                ['id' => 'by-tool', 'type' => 'breakdown', 'label' => 'Runs by tool', 'settings' => ['resource' => 'runs', 'group' => 'tool']],
                ['id' => 'all', 'type' => 'stat', 'label' => 'All runs', 'settings' => ['resource' => 'runs', 'followsList' => false]],
                ['id' => 'matching', 'type' => 'stat', 'label' => 'Matching'],
                ['id' => 'everything', 'type' => 'stat', 'label' => 'Everything', 'settings' => ['followsList' => false, 'period' => false]],
                ['id' => 'in-period', 'type' => 'stat', 'label' => 'In the period'],
            ],
            'list' => 'collapsed',
            'period' => ['options' => ['7d', '30d', '90d', 'month', 'year'], 'default' => '30d'],
        ], $this->summary('runs'));
        $this->assertNull($this->summary('posts'));
        $this->assertValidDefinition($this->app->make(DefinitionBuilder::class)->build(new GenericUser(['id' => 1, 'name' => 'Ada'])));
    }

    public function test_a_resource_the_user_can_not_list_has_no_summary(): void
    {
        RunPolicy::$denied = ['viewAny'];

        $this->assertNull($this->summary('runs'));
    }

    /**
     * A resource with a summary.
     *
     * @param  list<Widget>|Summary  $summary
     */
    private static function summarized(array|Summary $summary): Resource
    {
        return new class($summary) extends Resource
        {
            /**
             * @param  list<Widget>|Summary  $declared
             */
            public function __construct(private array|Summary $declared) {}

            public function id(): string
            {
                return 'logs';
            }

            public function model(): string
            {
                return Run::class;
            }

            public function fields(): array
            {
                return [Text::make('title')];
            }

            public function summary(): array|Summary
            {
                return $this->declared;
            }
        };
    }

    public function test_the_widgets_of_a_summary_are_those_of_the_user(): void
    {
        Gate::define('logs.costs', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 1);
        Panneau::resources([self::summarized(Summary::make(fn (?Authenticatable $user): array => [
            Widget::stat('overdue')->using(OverdueWidget::class)->can('logs.costs'),
            ...($user?->getAuthIdentifier() === 2 ? [Widget::list('latest')->resource('logs')] : []),
        ]))]);

        $this->assertSame([['id' => 'overdue', 'type' => 'stat']], $this->summary('logs')['widgets'] ?? null);
        $this->assertSame([['id' => 'latest', 'type' => 'list', 'settings' => ['resource' => 'logs']]], $this->summary('logs', new GenericUser(['id' => 2, 'name' => 'Grace']))['widgets'] ?? null);
    }

    public function test_a_summary_of_widgets_without_options(): void
    {
        Panneau::resources([self::summarized([Widget::stat('overdue')->using(OverdueWidget::class)])]);

        $this->assertSame(['widgets' => [['id' => 'overdue', 'type' => 'stat']]], $this->summary('logs'));
    }

    /**
     * @return array<string, array{list<Widget>|Summary, string}>
     */
    public static function invalidSummaries(): array
    {
        return [
            'a widget without data' => [[Widget::stat('a')], 'The widget "a" has no data: give it a resource() or using().'],
            'two widgets of the same id' => [[Widget::stat('a')->resource('logs'), Widget::list('a')->resource('logs')], 'The summary of "logs" has two widgets "a": ids are unique.'],
            'a widget on another resource that follows the list' => [[Widget::list('a')->resource('posts')->followsList()], 'The widget "a" reads "posts": it doesn\'t follow the list of "logs" (followsList()).'],
        ];
    }

    /**
     * @param  list<Widget>|Summary  $summary
     */
    #[DataProvider('invalidSummaries')]
    public function test_the_widgets_of_a_summary_are_checked(array|Summary $summary, string $message): void
    {
        Panneau::resources([self::summarized($summary)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->summary('logs');
    }

    public function test_only_the_widgets_of_a_summary_follow_a_list(): void
    {
        Panneau::dashboard([Widget::stat('a')->resource('runs')->followsList(false)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The widget "a" follows a list (followsList()): only the widgets of the summary of a resource do.');

        $this->summary('runs');
    }

    /**
     * A resource of runs whose summary has a period, with a list of its own.
     */
    private static function withPeriod(?string $filter, bool $dateRange = true): Resource
    {
        return new class($filter, $dateRange) extends Resource
        {
            public function __construct(private ?string $filter, private bool $dateRange) {}

            public function id(): string
            {
                return 'logs';
            }

            public function model(): string
            {
                return Run::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), DateTime::make('ran_at')];
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->filters($this->dateRange ? [DateRangeFilter::make('ran_at')] : [DateFilter::make('ran_at')]);
            }

            public function summary(): Summary
            {
                return Summary::make([Widget::stat('runs')->resource('logs')])->period(['7d', '30d'], filter: $this->filter);
            }
        };
    }

    public function test_the_period_of_a_summary(): void
    {
        Panneau::resources([self::withPeriod('ran_at')]);

        $this->assertSame(['options' => ['7d', '30d'], 'filter' => 'ran_at'], $this->summary('logs')['period'] ?? null);
    }

    /**
     * @return array<string, array{string|null, bool, string}>
     */
    public static function invalidSummaryPeriods(): array
    {
        return [
            'a filter that is not a date-range' => ['published_at', true, 'The period of the summary of "logs" sets the filter "published_at", which isn\'t a date-range filter of its list.'],
            'a list without a date-range filter' => [null, false, 'The period of the summary of "logs" is a date-range filter of its list, which has none.'],
        ];
    }

    #[DataProvider('invalidSummaryPeriods')]
    public function test_the_period_of_a_summary_is_a_date_range_filter_of_its_list(?string $filter, bool $dateRange, string $message): void
    {
        Panneau::resources([self::withPeriod($filter, $dateRange)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->summary('logs');
    }
}
