<?php

namespace Panneau\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Panneau\Dashboard\BreakdownWidget;
use Panneau\Dashboard\ListWidget;
use Panneau\Dashboard\MetricWidget;
use Panneau\Dashboard\Row;
use Panneau\Dashboard\SeriesWidget;
use Panneau\Dashboard\Source;
use Panneau\Dashboard\StatWidget;
use Panneau\Dashboard\TableWidget;
use Panneau\Dashboard\Widget;
use Panneau\Dashboard\WidgetShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TestApp\Dashboard\OverdueWidget;

class WidgetTest extends TestCase
{
    public function test_a_stat_on_a_resource(): void
    {
        $widget = Widget::stat('to-judge')
            ->label('To judge')
            ->resource('posts', ['filter[status]' => 'draft', 'filter[tags][]' => ['a', 'b']]);

        $this->assertSame('posts', $widget->getResource());
        $this->assertSame([
            'id' => 'to-judge',
            'type' => 'stat',
            'label' => 'To judge',
            'settings' => ['resource' => 'posts', 'query' => ['filter[status]' => 'draft', 'filter[tags][]' => ['a', 'b']]],
        ], $widget->toDefinition('posts'));
    }

    public function test_widgets_on_the_aggregates_of_a_resource(): void
    {
        $this->assertSame(
            ['id' => 'per-day', 'type' => 'series', 'settings' => ['resource' => 'posts', 'query' => ['filter[status]' => 'published'], 'group' => 'day:published_at']],
            Widget::series('per-day')->resource('posts', ['filter[status]' => 'published'])->per('day', 'published_at')->toDefinition('posts'),
        );
        $this->assertSame(
            ['id' => 'by-status', 'type' => 'breakdown', 'settings' => ['resource' => 'posts', 'measure' => 'sum:rating', 'group' => 'status']],
            Widget::breakdown('by-status')->by('status')->sum('rating')->resource('posts')->toDefinition('posts'),
        );
        $budget = Widget::stat('budget')->resource('posts')->sum('budget')->target(100000);
        $this->assertTrue($budget->readsAggregates());
        $this->assertSame(['id' => 'budget', 'type' => 'stat', 'settings' => ['resource' => 'posts', 'measure' => 'sum:budget', 'target' => 100000]], $budget->toDefinition('posts'));
        // A stat that counts reads the total of the list
        $count = Widget::stat('drafts')->resource('posts')->count();
        $this->assertFalse($count->readsAggregates());
        $this->assertSame(['id' => 'drafts', 'type' => 'stat', 'settings' => ['resource' => 'posts', 'measure' => 'count']], $count->toDefinition('posts'));
        $this->assertTrue(Widget::breakdown('a')->readsAggregates());
        $this->assertFalse(Widget::list('a')->readsAggregates());
    }

    public function test_widgets_with_data_of_their_own_leave_out_what_they_do_not_set(): void
    {
        $this->assertSame(['id' => 'per-day', 'type' => 'series', 'width' => 8], Widget::series('per-day')->width(8)->using(fn (): array => [])->toDefinition());
        $this->assertSame(
            ['id' => 'map', 'type' => 'map', 'settings' => ['city' => 'Montréal']],
            Widget::make('map', 'map')->settings(['city' => 'Montréal'])->toDefinition(),
        );
        // Empty settings would be a JSON list where the protocol wants an object
        $this->assertSame(['id' => 'map', 'type' => 'map'], Widget::make('map', 'map')->settings([])->toDefinition());
        $this->assertSame(['id' => 'latest', 'type' => 'list', 'settings' => ['resource' => 'posts']], Widget::list('latest')->resource('posts')->toDefinition('posts'));
    }

    public function test_a_metric_on_a_resource(): void
    {
        $widget = Widget::metric('cost')->label('AI cost')
            ->resource('runs', ['filter[tool]' => 'summaries'])
            ->sum('cost')
            ->points('day', 'ran_at')
            ->by('tool')
            ->target(500)
            ->goodWhen('down')
            ->currency('USD')
            ->format(['maximumFractionDigits' => 2])
            ->hideEmpty()
            ->period('ran_at');

        $this->assertTrue($widget->readsAggregates());
        $this->assertTrue($widget->readsResources());
        $this->assertSame([
            'id' => 'cost',
            'type' => 'metric',
            'label' => 'AI cost',
            'settings' => [
                'resource' => 'runs',
                'query' => ['filter[tool]' => 'summaries'],
                'measure' => 'sum:cost',
                'target' => 500,
                'points' => 'day:ran_at',
                'by' => 'tool',
                'format' => ['style' => 'currency', 'currency' => 'USD', 'maximumFractionDigits' => 2],
                'hideEmpty' => true,
                'goodWhen' => 'down',
                'period' => 'ran_at',
            ],
        ], $widget->toDefinition('runs'));
    }

    public function test_a_series_split_by_a_field_and_stacked(): void
    {
        $this->assertSame(
            ['id' => 'per-status', 'type' => 'series', 'settings' => ['resource' => 'runs', 'group' => 'week:ran_at', 'split' => 'status', 'stacked' => true]],
            Widget::series('per-status')->resource('runs')->per('week', 'ran_at')->split('status')->stacked()->toDefinition('runs'),
        );
    }

    public function test_a_series_of_several_sources(): void
    {
        $widget = Widget::series('messages')->label('Messages')->per('day', 'sent_at')->period('sent_at')->sources([
            Source::make('emails', 'Emails')->help('To members'),
            Source::make('texts', 'Text messages')
                ->query(['filter[channel]' => 'sms'])
                ->sum('segments')
                ->per('day', 'created_at')
                ->period(false)
                ->tone('info')
                ->alert(['filter[status]' => 'failed'], 'failed'),
        ]);

        $this->assertTrue($widget->readsResources());
        $this->assertSame([
            'id' => 'messages',
            'type' => 'series',
            'label' => 'Messages',
            'settings' => [
                'group' => 'day:sent_at',
                'sources' => [
                    ['resource' => 'emails', 'label' => 'Emails', 'help' => 'To members'],
                    [
                        'resource' => 'sms',
                        'label' => 'Text messages',
                        'query' => ['filter[channel]' => 'sms'],
                        'measure' => 'sum:segments',
                        'group' => 'day:created_at',
                        'tone' => 'info',
                        'period' => false,
                        'alert' => ['query' => ['filter[status]' => 'failed'], 'label' => 'failed'],
                    ],
                ],
                'period' => 'sent_at',
            ],
        ], $widget->toDefinition(null, ['emails', 'sms']));
    }

    public function test_a_table_of_rows(): void
    {
        $widget = Widget::table('logs')->label('Logs')->width(12)->points('day', 'ran_at')->goodWhen('down')->rows([
            Row::make('runs', 'Runs')->alert(['filter[status]' => 'failed'], 'failed', 'warning'),
            Row::make('runs', 'Cost')->sum('cost')->points('day', 'day')->period('ran_at'),
        ]);

        $this->assertTrue($widget->readsResources());
        $this->assertSame([
            'id' => 'logs',
            'type' => 'table',
            'label' => 'Logs',
            'width' => 12,
            'settings' => [
                'points' => 'day:ran_at',
                'rows' => [
                    ['resource' => 'runs', 'label' => 'Runs', 'alert' => ['query' => ['filter[status]' => 'failed'], 'label' => 'failed', 'tone' => 'warning']],
                    ['resource' => 'runs', 'label' => 'Cost', 'measure' => 'sum:cost', 'points' => 'day:day', 'period' => 'ran_at'],
                ],
                'goodWhen' => 'down',
            ],
        ], $widget->toDefinition());
    }

    public function test_formats_merge_key_by_key(): void
    {
        $widget = Widget::metric('size')->format(['maximumFractionDigits' => 1])->unit('megabyte', 'long');
        $this->assertSame(['maximumFractionDigits' => 1, 'style' => 'unit', 'unit' => 'megabyte', 'unitDisplay' => 'long'], $widget->toDefinition()['settings']['format']);
        // A currency replaces the unit, and null removes an option
        $widget->currency('CAD', 'narrowSymbol')->format(['maximumFractionDigits' => null]);
        $this->assertSame(['style' => 'currency', 'currency' => 'CAD', 'currencyDisplay' => 'narrowSymbol'], $widget->toDefinition()['settings']['format']);
        $this->assertSame(['style' => 'percent'], Widget::stat('rate')->currency('USD')->percent()->toDefinition()['settings']['format']);
        $this->assertSame(
            ['notation' => 'compact', 'signDisplay' => 'exceptZero', 'style' => 'unit', 'unit' => 'kilometer-per-hour'],
            Widget::series('speed')->format(['notation' => 'compact', 'signDisplay' => 'exceptZero'])->unit('kilometer-per-hour')->toDefinition()['settings']['format'],
        );
        // Without a format, false flags are left out
        $this->assertSame(['id' => 'a', 'type' => 'series'], Widget::series('a')->hideEmpty(false)->stacked(false)->toDefinition());
    }

    /**
     * @return array<string, array{\Closure(): mixed, string}>
     */
    public static function invalidWidgets(): array
    {
        return [
            'no id' => [fn (): Widget => Widget::stat(''), 'A widget needs an id.'],
            'no type' => [fn (): Widget => Widget::make('map', ''), 'The widget "map" needs a type.'],
            'a width out of the grid' => [fn (): Widget => Widget::stat('a')->width(13), 'The widget "a" has the width 13: widths go from 1 to 12 columns.'],
            'a period of a year' => [fn (): Widget => Widget::series('a')->per('year', 'created_at'), 'The series "a" is per "year": periods are day, week or month.'],
            'a target of 0' => [fn (): Widget => Widget::stat('a')->target(0), 'The stat "a" has the target 0: targets are above 0.'],
            'a query that pages' => [fn (): Widget => Widget::list('a')->resource('posts', ['perPage' => 10]), 'The query of the widget "a" sets "perPage", which the app sets.'],
            'a query with an object' => [fn (): Widget => Widget::list('a')->resource('posts', ['filter[status]' => ['x' => new stdClass]]), 'The query of the widget "a" has a value of "filter[status]"'],
            'settings of a built-in type' => [fn (): Widget => Widget::stat('a')->settings(['x' => 1]), 'The stat "a" has no settings'],
            'data that is not WidgetData' => [fn (): Widget => Widget::stat('a')->using(stdClass::class), "The data of the widget \"a\" is stdClass, which doesn't implement"],
            'a resource and data' => [fn (): Widget => Widget::stat('a')->using(OverdueWidget::class)->resource('posts'), 'The widget "a" has data of its own (using()): it can\'t also read a resource.'],
            'data and a resource' => [fn (): Widget => Widget::stat('a')->resource('posts')->using(OverdueWidget::class), 'The widget "a" reads a resource: it can\'t also have data of its own.'],
            'a cache of no time' => [fn (): Widget => Widget::stat('a')->cache(0), 'The widget "a" is cached for 0 seconds: give it at least one.'],
            // Combined widgets (panneau-js decision 0089)
            'points of a year' => [fn (): Widget => Widget::metric('a')->points('year', 'ran_at'), 'The metric "a" has points per "year": periods are day, week or month.'],
            'a metric good sideways' => [fn (): Widget => Widget::metric('a')->goodWhen('left'), 'The metric "a" is good when "left": give up or down.'],
            'no sources' => [fn (): Widget => Widget::series('a')->sources([]), 'The series "a" has 0 sources: give it from 1 to 5.'],
            'six sources' => [fn (): Widget => Widget::series('a')->sources(array_fill(0, 6, Source::make('runs', 'Runs'))), 'The series "a" has 6 sources: give it from 1 to 5.'],
            'nine rows' => [fn (): Widget => Widget::table('a')->rows(array_fill(0, 9, Row::make('runs', 'Runs'))), 'The table "a" has 9 rows: give it from 1 to 8.'],
            'a row among sources' => [fn (): Widget => Widget::series('a')->sources([Row::make('runs', 'Runs')]), 'The series "a" has a Panneau\Dashboard\Row in its sources(): give it Panneau\Dashboard\Source builders.'],
            'sources and a resource' => [fn (): Widget => Widget::series('a')->resource('runs')->sources([Source::make('runs', 'Runs')]), 'The series "a" reads a resource: it can\'t also have sources().'],
            'a resource and sources' => [fn (): Widget => Widget::series('a')->sources([Source::make('runs', 'Runs')])->resource('runs'), 'The series "a" reads the resources of its sources(): it can\'t also read a resource.'],
            'rows and data' => [fn (): Widget => Widget::table('a')->rows([Row::make('runs', 'Runs')])->using(OverdueWidget::class), 'The table "a" reads the resources of its rows(): it can\'t also have data of its own.'],
            'data and rows' => [fn (): Widget => Widget::table('a')->using(OverdueWidget::class)->rows([Row::make('runs', 'Runs')]), 'The table "a" has data of its own (using()): it can\'t also have rows().'],
            'a format with an unknown option' => [fn (): Widget => Widget::metric('a')->format(['locale' => 'fr']), 'The format of the widget "a" has the option "locale"'],
            'a currency that is not one' => [fn (): Widget => Widget::metric('a')->currency('usd'), 'The format of the widget "a" has a wrong currency'],
            'a currency style without a currency' => [fn (): Widget => Widget::metric('a')->format(['style' => 'currency']), 'The format of the widget "a" has a wrong currency'],
            'a unit that is not one' => [fn (): Widget => Widget::metric('a')->unit('parsec'), 'The format of the widget "a" has a wrong unit'],
            'more minimum digits than maximum' => [fn (): Widget => Widget::metric('a')->format(['minimumFractionDigits' => 3, 'maximumFractionDigits' => 1]), 'The format of the widget "a" has a wrong minimumFractionDigits'],
            'a source without a label' => [fn (): Source => Source::make('runs', ''), 'The source on "runs" needs a label.'],
            'a row without a resource' => [fn (): Row => Row::make('', 'Runs'), 'The row "Runs" needs a resource.'],
            'a source per year' => [fn (): Source => Source::make('runs', 'Runs')->per('year', 'ran_at'), 'The source "Runs" is per "year": periods are day, week or month.'],
            'a source with a wrong tone' => [fn (): Source => Source::make('runs', 'Runs')->tone('Red'), 'The source "Runs" has the tone "Red"'],
            'a row whose query pages' => [fn (): Row => Row::make('runs', 'Runs')->query(['cursor' => 'x']), 'The query of the row "Runs" sets "cursor", which the app sets.'],
            'an alert of everything' => [fn (): Row => Row::make('runs', 'Runs')->alert([], 'runs'), 'The alert of the row "Runs" counts some of its items: give it the filters of a query.'],
            'an alert without a label' => [fn (): Row => Row::make('runs', 'Runs')->alert(['filter[status]' => 'failed'], ''), 'The alert of the row "Runs" needs a label'],
            'an alert whose query pages' => [fn (): Source => Source::make('runs', 'Runs')->alert(['page' => 2], 'runs'), 'The query of the alert of the source "Runs" sets "page"'],
            'a row without a period' => [fn (): Row => Row::make('runs', 'Runs')->period(''), 'The row "Runs" follows the period with the filter ""'],
        ];
    }

    /**
     * The methods of the types that have them, and not of the others: each builder only has those
     * that apply to its type.
     *
     * @return array<string, array{Widget, list<string>, list<string>}>
     */
    public static function methodsOfTypes(): array
    {
        $resource = ['resource'];
        $measures = ['count', 'sum'];
        $format = ['format', 'currency', 'unit', 'percent', 'hideEmpty'];

        return [
            'a stat' => [Widget::stat('a'), [...$resource, ...$measures, ...$format, 'target'], ['per', 'by', 'points', 'split', 'sources', 'rows', 'goodWhen', 'stacked']],
            'a series' => [Widget::series('a'), [...$resource, ...$measures, ...$format, 'per', 'split', 'sources', 'stacked'], ['by', 'points', 'rows', 'target', 'goodWhen']],
            'a breakdown' => [Widget::breakdown('a'), [...$resource, ...$measures, ...$format, 'by'], ['per', 'points', 'split', 'sources', 'rows', 'target', 'goodWhen', 'stacked']],
            'a list' => [Widget::list('a'), [...$resource, ...$format], [...$measures, 'per', 'by', 'points', 'split', 'sources', 'rows', 'target', 'goodWhen', 'stacked']],
            'a metric' => [Widget::metric('a'), [...$resource, ...$measures, ...$format, 'by', 'points', 'target', 'goodWhen'], ['per', 'split', 'sources', 'rows', 'stacked']],
            'a table' => [Widget::table('a'), [...$format, 'rows', 'points', 'goodWhen'], [...$resource, ...$measures, 'per', 'by', 'split', 'sources', 'target', 'stacked']],
            'a widget of a plugin' => [Widget::make('a', 'map'), [], [...$resource, ...$measures, ...$format, 'per', 'by', 'points', 'split', 'sources', 'rows', 'target', 'goodWhen', 'stacked']],
        ];
    }

    /**
     * @param  list<string>  $has
     * @param  list<string>  $hasNot
     */
    #[DataProvider('methodsOfTypes')]
    public function test_each_builder_has_the_methods_of_its_type(Widget $widget, array $has, array $hasNot): void
    {
        foreach ([...$has, 'label', 'width', 'settings', 'followsList', 'period', 'using', 'cache', 'can'] as $method) {
            $this->assertTrue(is_callable([$widget, $method]), sprintf('%s has %s().', $widget->type(), $method));
        }
        foreach ($hasNot as $method) {
            $this->assertFalse(is_callable([$widget, $method]), sprintf('%s has no %s().', $widget->type(), $method));
        }
    }

    public function test_make_gives_the_builder_of_a_built_in_type(): void
    {
        $this->assertInstanceOf(StatWidget::class, Widget::stat('a'));
        $this->assertInstanceOf(SeriesWidget::class, Widget::make('a', 'series'));
        $this->assertInstanceOf(BreakdownWidget::class, Widget::make('a', 'breakdown'));
        $this->assertInstanceOf(ListWidget::class, Widget::make('a', 'list'));
        $this->assertInstanceOf(MetricWidget::class, Widget::make('a', 'metric'));
        $this->assertInstanceOf(TableWidget::class, Widget::make('a', 'table'));
        $this->assertSame(Widget::class, Widget::make('a', 'map')::class);
        // Chains keep the builder of the type
        $this->assertInstanceOf(SeriesWidget::class, Widget::series('a')->label('Runs')->resource('runs')->count()->period('ran_at'));
    }

    /**
     * @param  \Closure(): mixed  $make
     */
    #[DataProvider('invalidWidgets')]
    public function test_invalid_widgets_throw(\Closure $make, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $make();
    }

    /**
     * The same cases as the tests of `checkWidgetData()` in `@panneau/protocol`.
     *
     * @return array<string, array{string, array<array-key, mixed>, list<string>}>
     */
    public static function shapes(): array
    {
        return [
            'a stat' => ['stat', ['value' => 12, 'help' => '3 more than last week', 'tone' => 'warning', 'url' => '/x'], []],
            'a stat with nulls' => ['stat', ['value' => 0.5, 'help' => null, 'tone' => null], []],
            'a series' => ['series', ['period' => 'day', 'points' => [['date' => '2026-10-05', 'value' => 4], ['date' => '2026-10-06', 'value' => 8]], 'total' => 12], []],
            'a series without points' => ['series', ['period' => 'month', 'points' => []], []],
            'a list' => ['list', ['items' => [['title' => 'Spring contest', 'description' => null, 'meta' => '12 answers', 'url' => '/contests/1', 'badge' => ['label' => 'Open', 'tone' => 'success']], ['title' => 'Fall contest']], 'empty' => 'No open contests.'], []],
            'an empty list' => ['list', ['items' => []], []],
            'a widget of a plugin' => ['map', ['anything' => [1, 2]], []],
            'a stat as text' => ['stat', ['value' => '12'], ['value']],
            'a stat with a wrong tone and url' => ['stat', ['value' => 12, 'tone' => 'Warning!', 'url' => 3], ['tone', 'url']],
            'a stat as a list' => ['stat', [1, 2], ['']],
            'a series of a year' => ['series', ['period' => 'year', 'points' => ['x' => 1]], ['period', 'points']],
            'a series going back' => ['series', ['period' => 'week', 'points' => [['date' => '2026-10-12', 'value' => 1], ['date' => '2026-10-05', 'value' => 2], ['date' => '2026-10-19', 'value' => 'x']], 'total' => '3'], ['points.1.date', 'points.2.value', 'total']],
            'a list without titles' => ['list', ['items' => [['description' => 'No title', 'badge' => ['tone' => 'success']]], 'empty' => 1], ['items.0.title', 'items.0.badge', 'empty']],
            'a list too long' => ['list', ['items' => array_map(fn (int $index): array => ['title' => (string) $index], range(1, 6))], ['items']],
            'a widget of a plugin as a list' => ['map', ['a', 'b'], ['']],
            'a stat with a target' => ['stat', ['value' => 12, 'target' => 20], []],
            'a stat with a target of 0' => ['stat', ['value' => 12, 'target' => 0], ['target']],
            'a breakdown' => ['breakdown', ['items' => [['label' => 'Failed', 'value' => 3, 'tone' => 'danger', 'url' => '/x', 'key' => 'failed'], ['label' => 'None', 'value' => 1, 'key' => null], ['label' => 'Yes', 'value' => 2, 'key' => true]], 'total' => 42, 'other' => 5], []],
            'an empty breakdown' => ['breakdown', ['items' => []], []],
            'a breakdown with wrong parts' => ['breakdown', ['items' => [['value' => 'x', 'tone' => 'Red', 'key' => ['a']], 'x'], 'other' => '5'], ['items.0.label', 'items.0.value', 'items.0.tone', 'items.0.key', 'items.1', 'other']],
            'a breakdown too long' => ['breakdown', ['items' => array_map(fn (int $index): array => ['label' => (string) $index, 'value' => $index], range(1, 11))], ['items']],
            // Combined widgets (panneau-js decision 0089), as in `combinedWidgets.test.ts`
            'a metric' => ['metric', [
                'value' => 40, 'previous' => 32, 'help' => 'Sent to members', 'url' => '/emails',
                'points' => [['date' => '2026-10-05', 'value' => 12], ['date' => '2026-10-06', 'value' => 28]],
                'period' => 'day', 'parts' => [['label' => 'Sent', 'value' => 38, 'key' => 'sent', 'tone' => 'success']], 'other' => 2,
                'format' => ['style' => 'currency', 'currency' => 'USD'],
            ], []],
            'a metric of a value' => ['metric', ['value' => 3], []],
            'a wrong metric' => ['metric', [
                'value' => '3', 'previous' => 'x',
                'points' => [['date' => '2026-10-06', 'value' => 1], ['date' => '2026-10-05', 'value' => 1]],
                'parts' => [['label' => 'A']], 'format' => ['style' => 'currency'],
            ], ['value', 'format.currency', 'previous', 'points.1.date', 'parts.0.value']],
            'a metric with too many parts' => ['metric', ['value' => 1, 'parts' => array_map(fn (int $index): array => ['label' => (string) $index, 'value' => $index], range(1, 11)), 'period' => 'year'], ['period', 'parts']],
            'a table' => ['table', [
                'columns' => [['key' => 'cost', 'label' => 'Cost', 'format' => ['style' => 'currency', 'currency' => 'USD']], ['key' => 'calls', 'label' => 'Calls', 'align' => 'end']],
                'rows' => [
                    ['label' => 'Summaries', 'url' => '/runs', 'cells' => ['cost' => 9.08, 'calls' => ['value' => 6, 'tone' => 'danger', 'help' => 'failed']], 'points' => [['date' => '2026-10-05', 'value' => 3]]],
                    ['label' => 'Translations', 'cells' => ['cost' => null]],
                    ['label' => 'Nothing yet', 'cells' => []],
                ],
            ], []],
            'a wrong table' => ['table', [
                'columns' => [['key' => 'a', 'label' => 'A'], ['key' => 'a', 'label' => 'B', 'align' => 'middle']],
                'rows' => [['label' => 'R', 'cells' => ['a' => '3']]],
            ], ['columns.1.key', 'columns.1.align', 'rows.0.cells.a']],
            'a table without columns' => ['table', ['columns' => [], 'rows' => []], ['columns']],
            'a table with wrong cells and formats' => ['table', [
                'columns' => [['key' => 'a', 'label' => 'A', 'format' => ['unit' => 'parsec']]],
                'rows' => [['label' => 'R', 'cells' => ['a' => ['tone' => 'danger'], 'b' => ['value' => 1, 'format' => ['currency' => 'usd']]], 'points' => 'x'], ['cells' => [1]]],
                'format' => ['notation' => 'scientific'],
            ], ['columns.0.format.unit', 'rows.0.cells.a', 'rows.0.cells.b', 'rows.0.points', 'rows.1.label', 'rows.1.cells', 'format.notation']],
            'a table too large' => ['table', [
                'columns' => array_map(fn (int $index): array => ['key' => (string) $index, 'label' => (string) $index], range(1, 7)),
                'rows' => array_map(fn (int $index): array => ['label' => (string) $index, 'cells' => []], range(1, 9)),
            ], ['columns', 'rows']],
            'a series of several lines' => ['series', [
                'period' => 'day', 'points' => [],
                'series' => [
                    ['label' => 'Emails', 'points' => [['date' => '2026-10-05', 'value' => 3]], 'total' => 3, 'alert' => ['value' => 4, 'label' => 'failed', 'tone' => 'danger']],
                    ['label' => 'Texts', 'points' => [], 'help' => 'Since Monday', 'url' => '/texts', 'key' => 'texts'],
                ],
                'format' => ['style' => 'unit', 'unit' => 'megabyte'],
            ], []],
            'a series with wrong lines' => ['series', [
                'period' => 'day', 'points' => [],
                'series' => [['points' => [['date' => 'x', 'value' => 1]], 'alert' => ['value' => 'x']]],
            ], ['series.0.label', 'series.0.points.0.date', 'series.0.alert.value', 'series.0.alert.label']],
            'a series with too many lines' => ['series', ['period' => 'day', 'points' => [], 'series' => array_fill(0, 7, ['label' => 'A', 'points' => []])], ['series']],
            'a series without its points' => ['series', ['period' => 'day', 'series' => [['label' => 'A', 'points' => []]]], ['points']],
            'a stat and a breakdown with formats' => ['stat', ['value' => 1, 'format' => ['style' => 'percent', 'minimumFractionDigits' => 1]], []],
            'a breakdown with a wrong format' => ['breakdown', ['items' => [], 'format' => 'USD'], ['format']],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $wrong
     */
    #[DataProvider('shapes')]
    public function test_the_shape_of_the_data_of_each_type(string $type, array $data, array $wrong): void
    {
        $this->assertSame($wrong, WidgetShape::check($type, $data));
    }
}
