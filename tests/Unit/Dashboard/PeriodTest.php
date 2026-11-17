<?php

namespace Panneau\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Panneau\Dashboard\Period;
use Panneau\Dashboard\Summary;
use Panneau\Dashboard\Widget;
use Panneau\Dashboard\WidgetPeriod;
use Panneau\Navigation\Page;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The period of an overview (panneau-js decision 0084).
 */
class PeriodTest extends TestCase
{
    public function test_a_period_in_the_definition(): void
    {
        $this->assertSame(['options' => ['7d', '30d']], (new Period(['7d', '30d']))->toDefinition());
        $this->assertSame(['options' => ['30d', 'year'], 'default' => 'year', 'filter' => 'sent_at'], (new Period(['30d', 'year'], 'year', 'sent_at'))->toDefinition());
        $this->assertSame(
            ['component' => 'dashboard', 'label' => 'Activity', 'period' => ['options' => ['7d', 'month'], 'default' => 'month']],
            Page::dashboard('activity')->label('Activity')->period(['7d', 'month'], default: 'month')->toDefinition(),
        );
        $this->assertSame('created_at', Summary::make([])->period(['7d'], filter: 'created_at')->getPeriod()?->filter);
        $this->assertSame(
            ['id' => 'a', 'type' => 'stat', 'settings' => ['resource' => 'posts', 'period' => 'published_at']],
            Widget::stat('a')->resource('posts')->period('published_at')->toDefinition('posts'),
        );
        $this->assertSame(['id' => 'a', 'type' => 'stat', 'settings' => ['period' => false]], Widget::stat('a')->period(false)->toDefinition());
    }

    /**
     * @return array<string, array{\Closure(): mixed, string}>
     */
    public static function invalidPeriods(): array
    {
        return [
            'no options' => [fn (): Period => new Period([]), 'A period needs options, among 7d, 30d, 90d, month, year.'],
            'an unknown option' => [fn (): Period => new Period(['7d', 'decade']), 'The period "decade" isn\'t one of 7d, 30d, 90d, month, year.'],
            'an option twice' => [fn (): Period => new Period(['7d', '7d']), 'Each period is offered once.'],
            'a default that is not an option' => [fn (): Period => new Period(['7d'], '30d'), 'The default period "30d" isn\'t one of the options.'],
            'a page that is not a dashboard' => [fn (): Page => Page::make('reports', 'reports')->period(['7d']), 'The page "reports" is a "reports": only a dashboard page (Page::dashboard()) has a period.'],
            'a widget with an empty filter' => [fn (): Widget => Widget::stat('a')->period(''), 'The widget "a" follows the period with the filter "": give the id of a date-range filter, or false.'],
        ];
    }

    /**
     * @param  \Closure(): mixed  $make
     */
    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_throw(\Closure $make, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $make();
    }

    /**
     * The cases of `readWidgetDataQuery()` in `@panneau/protocol`.
     *
     * @return array<string, array{mixed, WidgetPeriod|string|null}>
     */
    public static function readPeriods(): array
    {
        $wrongOption = 'The period must be one of 7d, 30d, 90d, month and year.';
        $wrongDays = 'The period needs its first and last days, from before to.';

        return [
            'none' => [null, null],
            'a value that is not the three parameters' => ['30d', null],
            'a period' => [['option' => '30d', 'from' => '2026-10-19', 'to' => '2026-11-17'], new WidgetPeriod('30d', '2026-10-19', '2026-11-17')],
            'one day' => [['option' => '7d', 'from' => '2026-11-17', 'to' => '2026-11-17'], new WidgetPeriod('7d', '2026-11-17', '2026-11-17')],
            'an unknown option' => [['option' => 'decade', 'from' => '2026-01-01', 'to' => '2026-12-31'], $wrongOption],
            'no option' => [['from' => '2026-01-01', 'to' => '2026-12-31'], $wrongOption],
            'no first day' => [['option' => '7d', 'to' => '2026-11-17'], $wrongDays],
            'a day that does not exist' => [['option' => 'month', 'from' => '2026-02-01', 'to' => '2026-02-30'], $wrongDays],
            'a date and time' => [['option' => '7d', 'from' => '2026-11-11T00:00:00', 'to' => '2026-11-17'], $wrongDays],
            'from after to' => [['option' => '7d', 'from' => '2026-11-17', 'to' => '2026-11-11'], $wrongDays],
        ];
    }

    #[DataProvider('readPeriods')]
    public function test_the_period_of_a_request(mixed $parameter, WidgetPeriod|string|null $expected): void
    {
        $this->assertEquals($expected, WidgetPeriod::read($parameter));
    }

    public function test_the_days_of_a_period(): void
    {
        $period = new WidgetPeriod('7d', '2026-11-11', '2026-11-17');

        $this->assertSame('2026-11-11 00:00:00', $period->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-17 23:59:59', $period->end()->format('Y-m-d H:i:s'));
        $this->assertSame(['from' => '2026-11-11', 'to' => '2026-11-17'], $period->range());
    }
}
