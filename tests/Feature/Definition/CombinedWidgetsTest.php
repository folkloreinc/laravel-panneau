<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Panneau\Dashboard\Row;
use Panneau\Dashboard\Source;
use Panneau\Dashboard\Summary;
use Panneau\Dashboard\Widget;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Navigation\Page;
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
 * The combined widgets in the definition (panneau-js decision 0089): a `metric`, a `series` split
 * by a field or of several sources, and a `table` of rows, checked against the aggregates and the
 * filters of the lists they read, as `lintDefinition()` checks them.
 */
class CombinedWidgetsTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([RunsResource::class, AuthorsResource::class, PostsResource::class, TagsResource::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?Authenticatable $user = null): array
    {
        return $this->app->make(DefinitionBuilder::class)->build($user);
    }

    public function test_combined_widgets_on_resources(): void
    {
        Panneau::pages([Page::dashboard('activity')->period(['7d', '30d', 'month'])->widgets([
            Widget::metric('cost')->label('AI cost')->resource(RunsResource::class)->sum('amount')
                ->points('day', 'ran_at')->by('tool')->target(50000)->goodWhen('down')->currency('USD')->period('ran_at'),
            Widget::series('per-status')->label('Runs per status')->resource('runs')->per('day', 'ran_at')->split('status')->stacked(),
            Widget::series('messages')->label('Messages')->per('day', 'ran_at')->period('ran_at')->sources([
                Source::make(RunsResource::class, 'Runs')->help('Every tool'),
                Source::make('runs', 'Failed')->query(['filter[status][]' => 'failed'])->tone('danger')->period(false),
                Source::make('runs', 'Tokens')->sum('tokens')->alert(['filter[status][]' => 'failed'], 'failed'),
            ]),
            Widget::table('logs')->label('Logs')->width(12)->points('week', 'ran_at')->period('ran_at')->goodWhen('down')->hideEmpty()->rows([
                Row::make(RunsResource::class, 'Runs')->alert(['filter[status][]' => 'failed'], 'failed'),
                Row::make('runs', 'Cost')->sum('amount')->points('week', 'day'),
                Row::make('runs', 'By Ada')->query(['filter[author_id]' => 1])->period(false),
            ]),
            Widget::metric('storage')->label('Storage')->unit('megabyte')->hideEmpty()->using(OverdueWidget::class),
            Widget::table('own')->label('Own rows')->using(OverdueWidget::class),
        ])]);

        $definition = $this->definition();
        $this->assertSame([
            ['id' => 'cost', 'type' => 'metric', 'label' => 'AI cost', 'settings' => [
                'resource' => 'runs', 'measure' => 'sum:amount', 'target' => 50000, 'points' => 'day:ran_at', 'by' => 'tool',
                'format' => ['style' => 'currency', 'currency' => 'USD'], 'goodWhen' => 'down', 'period' => 'ran_at',
            ]],
            ['id' => 'per-status', 'type' => 'series', 'label' => 'Runs per status', 'settings' => [
                'resource' => 'runs', 'group' => 'day:ran_at', 'split' => 'status', 'stacked' => true,
            ]],
            ['id' => 'messages', 'type' => 'series', 'label' => 'Messages', 'settings' => [
                'group' => 'day:ran_at',
                'sources' => [
                    ['resource' => 'runs', 'label' => 'Runs', 'help' => 'Every tool'],
                    ['resource' => 'runs', 'label' => 'Failed', 'query' => ['filter[status][]' => 'failed'], 'tone' => 'danger', 'period' => false],
                    ['resource' => 'runs', 'label' => 'Tokens', 'measure' => 'sum:tokens', 'alert' => ['query' => ['filter[status][]' => 'failed'], 'label' => 'failed']],
                ],
                'period' => 'ran_at',
            ]],
            ['id' => 'logs', 'type' => 'table', 'label' => 'Logs', 'width' => 12, 'settings' => [
                'points' => 'week:ran_at',
                'rows' => [
                    ['resource' => 'runs', 'label' => 'Runs', 'alert' => ['query' => ['filter[status][]' => 'failed'], 'label' => 'failed']],
                    ['resource' => 'runs', 'label' => 'Cost', 'measure' => 'sum:amount', 'points' => 'week:day'],
                    ['resource' => 'runs', 'label' => 'By Ada', 'query' => ['filter[author_id]' => 1], 'period' => false],
                ],
                'hideEmpty' => true,
                'goodWhen' => 'down',
                'period' => 'ran_at',
            ]],
            ['id' => 'storage', 'type' => 'metric', 'label' => 'Storage', 'settings' => ['format' => ['style' => 'unit', 'unit' => 'megabyte'], 'hideEmpty' => true]],
            ['id' => 'own', 'type' => 'table', 'label' => 'Own rows'],
        ], $definition['pages']['activity']['widgets']);
        $this->assertValidDefinition($definition);
    }

    public function test_combined_widgets_in_a_summary(): void
    {
        Panneau::resources([new class extends RunsResource
        {
            public function id(): string
            {
                return 'logs';
            }

            public function summary(): Summary
            {
                return Summary::make([
                    Widget::metric('runs')->resource('logs')->points('day', 'ran_at'),
                    Widget::table('logs')->followsList(false)->rows([Row::make('logs', 'Logs'), Row::make('runs', 'Runs')]),
                ])->period(['30d']);
            }
        }]);

        Gate::policy(Run::class, RunPolicy::class);

        $definition = $this->definition(new GenericUser(['id' => 1, 'name' => 'Ada']));
        $summary = collect($definition['resources'])->firstWhere('id', 'logs')['summary'];
        $this->assertSame([
            ['id' => 'runs', 'type' => 'metric', 'settings' => ['resource' => 'logs', 'points' => 'day:ran_at']],
            ['id' => 'logs', 'type' => 'table', 'settings' => ['rows' => [['resource' => 'logs', 'label' => 'Logs'], ['resource' => 'runs', 'label' => 'Runs']], 'followsList' => false]],
        ], $summary['widgets']);
        $this->assertValidDefinition($definition);
    }

    public function test_combined_widgets_on_fields_the_user_can_not_see_are_left_out(): void
    {
        Panneau::dashboard([
            Widget::metric('margin')->resource('runs')->sum('margin'),
            Widget::series('margins')->per('day', 'ran_at')->sources([Source::make('runs', 'Runs'), Source::make('runs', 'Margin')->sum('margin')]),
            Widget::table('rows')->rows([Row::make('runs', 'Margin')->sum('margin')]),
            Widget::table('visible')->rows([Row::make('runs', 'Runs')]),
        ]);

        $this->assertSame(['visible'], array_column($this->definition()['dashboard']['widgets'], 'id'));
    }

    /**
     * @return array<string, array{Widget, string}>
     */
    public static function invalidWidgets(): array
    {
        return [
            'a metric on a resource without aggregates' => [Widget::metric('a')->resource('posts'), 'The metric "a" reads the aggregates of "posts", whose list declares none'],
            'a metric with points of a date that is not declared' => [Widget::metric('a')->resource('runs')->points('day', 'created_at'), 'The metric "a" has points by "created_at" of "runs", which isn\'t in the dates of the aggregates of its list.'],
            'a metric with parts by a field that is not declared' => [Widget::metric('a')->resource('runs')->by('title'), 'The metric "a" has parts by "title" of "runs", which isn\'t in the groupBy of the aggregates of its list.'],
            'a metric with a sum that is not declared' => [Widget::metric('a')->resource('runs')->sum('title'), 'The metric "a" sums "title" of "runs", which isn\'t in the sum of the aggregates of its list.'],
            'a metric with points and no resource' => [Widget::metric('a')->points('day', 'ran_at')->using(OverdueWidget::class), 'The widget "a" measures a resource (count(), sum(), per(), by(), points(), split() or target()) without one'],
            'a table with points and no rows' => [Widget::table('a')->points('day', 'ran_at')->using(OverdueWidget::class), 'The widget "a" measures a resource'],
            'a series split without a resource' => [Widget::series('a')->split('status')->using(OverdueWidget::class), 'The widget "a" measures a resource'],
            'a split by a field that is not declared' => [Widget::series('a')->resource('runs')->per('day', 'ran_at')->split('title'), 'The series "a" splits "runs" by "title", which isn\'t in the groupBy of the aggregates of its list.'],
            'a split by a field the list does not filter by' => [Widget::series('a')->resource('runs')->per('day', 'ran_at')->split('tool'), 'The series "a" splits "runs" by "tool", which isn\'t a filter of its list'],
            'a table without rows or data' => [Widget::table('a'), 'The widget "a" has no data: give it a resource() or using().'],
            'sources that sum' => [Widget::series('a')->per('day', 'ran_at')->sum('tokens')->sources([Source::make('runs', 'Runs')]), 'The series "a" reads the resources of its sources: count(), sum() and split() go on each of them.'],
            'a source on a resource that is not registered' => [Widget::series('a')->per('day', 'ran_at')->sources([Source::make('nope', 'Nope')]), 'The source "Nope" of the series "a" reads the resource nope, which is not registered.'],
            'a source on a resource without aggregates' => [Widget::series('a')->per('day', 'ran_at')->sources([Source::make('posts', 'Posts')]), 'The source "Posts" of the series "a" reads the aggregates of "posts", whose list declares none'],
            'a source that sums what is not declared' => [Widget::series('a')->per('day', 'ran_at')->sources([Source::make('runs', 'Runs')->sum('title')]), 'The source "Runs" of the series "a" sums "title" of "runs"'],
            'a line without periods' => [Widget::series('a')->sources([Source::make('runs', 'Runs')]), 'The source "Runs" of the series "a" needs per(): the days, weeks or months of a date, on the source or on the series.'],
            'a line of a date that is not declared' => [Widget::series('a')->sources([Source::make('runs', 'Runs')->per('day', 'created_at')]), 'The source "Runs" of the series "a" groups "runs" by "created_at", which isn\'t in the dates of the aggregates of its list.'],
            'a row with a filter its list does not have' => [Widget::table('a')->rows([Row::make('runs', 'Runs')->query(['filter[color]' => 'red'])]), 'The row "Runs" of the table "a" filters the list of "runs" by "color", which is not one of its filters.'],
            'an alert with a filter its list does not have' => [Widget::table('a')->rows([Row::make('runs', 'Runs')->alert(['filter[color]' => 'red'], 'red')]), 'The row "Runs" of the table "a" filters the list of "runs" by "color"'],
            'a row that follows a period without one' => [Widget::table('a')->rows([Row::make('runs', 'Runs')->period('ran_at')]), 'The row "Runs" of the table "a" follows a period (period()), but its overview has none.'],
            'lines on different periods' => [Widget::series('a')->per('day', 'ran_at')->sources([Source::make('runs', 'Days'), Source::make('runs', 'Weeks')->per('week', 'ran_at')]), 'The lines of the series "a" have different periods (day, week): they share one.'],
            'trends on different periods' => [Widget::table('a')->points('day', 'ran_at')->rows([Row::make('runs', 'Days'), Row::make('runs', 'Months')->points('month', 'day')]), 'The trends of the table "a" have different periods (day, month): they share one.'],
        ];
    }

    #[DataProvider('invalidWidgets')]
    public function test_invalid_combined_widgets_throw_when_the_definition_is_built(Widget $widget, string $message): void
    {
        Panneau::dashboard([$widget]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }

    public function test_sources_follow_the_period_with_a_date_range_filter(): void
    {
        Panneau::pages([Page::dashboard('activity')->period(['30d'])->widgets([
            Widget::table('a')->period('status')->rows([Row::make('runs', 'Runs')]),
        ])]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The row "Runs" of the table "a" follows the period with the filter "status", which isn\'t a date-range filter of the list of "runs".');

        $this->definition();
    }
}
