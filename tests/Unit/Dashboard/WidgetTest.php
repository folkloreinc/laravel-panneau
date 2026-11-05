<?php

namespace Panneau\Tests\Unit\Dashboard;

use InvalidArgumentException;
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

    /**
     * @return array<string, array{\Closure(): mixed, string}>
     */
    public static function invalidWidgets(): array
    {
        return [
            'no id' => [fn (): Widget => Widget::stat(''), 'A widget needs an id.'],
            'no type' => [fn (): Widget => Widget::make('map', ''), 'The widget "map" needs a type.'],
            'a width out of the grid' => [fn (): Widget => Widget::stat('a')->width(13), 'The widget "a" has the width 13: widths go from 1 to 12 columns.'],
            'a series on a resource' => [fn (): Widget => Widget::series('a')->resource('posts'), 'The series "a" can\'t read a resource: only a stat and a list can. Give it data with using().'],
            'a widget of a plugin on a resource' => [fn (): Widget => Widget::make('a', 'map')->resource('posts'), 'The map "a" can\'t read a resource'],
            'a query that pages' => [fn (): Widget => Widget::list('a')->resource('posts', ['perPage' => 10]), 'The query of the widget "a" sets "perPage", which the app sets.'],
            'a query with an object' => [fn (): Widget => Widget::list('a')->resource('posts', ['filter[status]' => ['x' => new stdClass]]), 'The query of the widget "a" has a value of "filter[status]"'],
            'settings of a built-in type' => [fn (): Widget => Widget::stat('a')->settings(['x' => 1]), 'The stat "a" has no settings'],
            'data that is not WidgetData' => [fn (): Widget => Widget::stat('a')->using(stdClass::class), "The data of the widget \"a\" is stdClass, which doesn't implement"],
            'a resource and data' => [fn (): Widget => Widget::stat('a')->using(OverdueWidget::class)->resource('posts'), 'The widget "a" has data of its own (using()): it can\'t also read a resource.'],
            'data and a resource' => [fn (): Widget => Widget::stat('a')->resource('posts')->using(OverdueWidget::class), 'The widget "a" reads a resource: it can\'t also have data of its own.'],
            'a cache of no time' => [fn (): Widget => Widget::stat('a')->cache(0), 'The widget "a" is cached for 0 seconds: give it at least one.'],
        ];
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
