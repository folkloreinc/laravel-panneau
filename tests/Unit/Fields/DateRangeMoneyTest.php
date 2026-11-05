<?php

namespace Panneau\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;
use Panneau\Fields\DateRange;
use Panneau\Fields\Money;
use Panneau\Fields\Sessions;
use Panneau\Fields\Text;
use Panneau\Money\Currency;
use Panneau\Money\Money as MoneyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `daterange` and `money` types, and the sessions (panneau-js decisions 0053 and 0065).
 */
class DateRangeMoneyTest extends TestCase
{
    public function test_date_range_settings(): void
    {
        $this->assertSame(['name' => 'dates', 'type' => 'daterange'], DateRange::make('dates')->toDefinition());
        // The daterange rule is implied by the type: it isn't in the definition
        $this->assertSame(
            ['name' => 'dates', 'type' => 'daterange', 'settings' => [
                'time' => true,
                'allDay' => true,
                'open' => 'end',
                'timeZone' => 'America/Toronto',
                'chooseTimeZone' => true,
            ]],
            DateRange::make('dates')->allDay()->open('end')->timeZone('America/Toronto')->chooseTimeZone()->toDefinition(),
        );
        $this->assertSame(['name' => 'dates', 'type' => 'daterange', 'settings' => ['open' => 'end']], DateRange::make('dates')->open()->toDefinition());
        $this->assertSame(
            ['name' => 'dates', 'type' => 'daterange'],
            DateRange::make('dates')->allDay()->chooseTimeZone()->time(false)->chooseTimeZone(false)->toDefinition(),
        );

        $field = DateRange::make('dates')->open('both');
        $this->assertTrue($field->isOpen('start'));
        $this->assertTrue($field->isOpen('end'));
        $this->assertFalse(DateRange::make('dates')->isOpen('end'));
        $this->assertFalse(DateRange::make('dates')->allDay(false)->allowsAllDay());
        $this->assertTrue(DateRange::make('dates')->allDay()->hasTime());
        $this->assertTrue(DateRange::isTimeZone('UTC'));
        $this->assertFalse(DateRange::isTimeZone('Mars/Olympus'));
    }

    public function test_money_settings(): void
    {
        $this->assertSame(['name' => 'price', 'type' => 'money'], Money::make('price')->toDefinition());
        $this->assertSame(['name' => 'price', 'type' => 'money', 'settings' => ['currency' => 'CAD']], Money::make('price')->currency('CAD')->toDefinition());
        // min() and max() are in minor units, in the definition as for any field
        $this->assertSame(
            ['name' => 'price', 'type' => 'money', 'rules' => [['rule' => 'min', 'value' => 100]], 'settings' => ['currencies' => ['CAD', 'USD']]],
            Money::make('price')->currencies(['CAD', 'USD'])->min(100)->toDefinition(),
        );

        $this->assertSame(['CAD'], Money::make('price')->currency('CAD')->currencies(['USD'])->allowed());
        $this->assertSame(['CAD', 'USD'], Money::make('price')->currencies(['CAD', 'USD'])->allowed());
        $this->assertSame(Currency::CODES, Money::make('price')->allowed());
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function invalidSettings(): array
    {
        return [
            'an unknown open bound' => [fn () => DateRange::make('dates')->open('middle')],
            'an unknown zone' => [fn () => DateRange::make('dates')->timeZone('Mars/Olympus')],
            'an offset as a zone' => [fn () => DateRange::make('dates')->timeZone('-05:00')],
            'a lowercase currency' => [fn () => Money::make('price')->currency('cad')],
            'an unknown currency' => [fn () => Money::make('price')->currencies(['CAD', 'XXX'])],
        ];
    }

    /**
     * @param  Closure(): mixed  $build
     */
    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_throw(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function test_ranges_are_serialized_as_days_or_date_times(): void
    {
        $start = CarbonImmutable::parse('2026-11-04 19:30:00', 'America/Toronto');
        $end = CarbonImmutable::parse('2026-11-04 21:00:00', 'America/Toronto');

        // Days, from the dates of a cast or the strings of a JSON column
        $this->assertSame(['start' => '2026-11-04', 'end' => '2026-11-04'], DateRange::make('dates')->serialize(['start' => $start, 'end' => $end]));
        $this->assertSame(['start' => '2026-11-04', 'end' => null], DateRange::make('dates')->serialize(['start' => '2026-11-04', 'end' => null]));
        // Date-times in ATOM, with their offset
        $this->assertSame(
            ['start' => '2026-11-04T19:30:00-05:00', 'end' => '2026-11-04T21:00:00-05:00'],
            DateRange::make('dates')->time()->serialize(['start' => $start, 'end' => $end, 'timeZone' => 'America/Toronto', 'allDay' => false]),
        );
        $this->assertSame(
            ['start' => '2026-11-05T00:30:00+00:00', 'end' => null],
            DateRange::make('dates')->time()->serialize((object) ['start' => '2026-11-05T00:30:00Z', 'end' => null]),
        );
        // The zone with chooseTimeZone(), all day with allDay()
        $this->assertSame(
            ['start' => '2026-11-04', 'end' => '2026-11-04', 'timeZone' => 'America/Toronto', 'allDay' => true],
            DateRange::make('dates')->allDay()->chooseTimeZone()->serialize(['start' => $start, 'end' => $end, 'timeZone' => 'America/Toronto', 'allDay' => true]),
        );
        $this->assertNull(DateRange::make('dates')->serialize(null));
        $this->assertNull(DateRange::make('dates')->serialize(['start' => null, 'end' => '']));
    }

    public function test_money_is_serialized_as_an_amount_and_a_currency(): void
    {
        $this->assertSame(['amount' => 1250, 'currency' => 'CAD'], Money::make('price')->serialize(MoneyValue::of(1250, 'CAD')));
        $this->assertSame(['amount' => 5, 'currency' => 'JPY'], Money::make('price')->serialize(['amount' => 5, 'currency' => 'JPY']));
        $this->assertNull(Money::make('price')->serialize(null));
        $this->assertSame(
            ['fr' => ['amount' => 100, 'currency' => 'EUR'], 'en' => null],
            Money::make('price')->localized()->serialize(['fr' => MoneyValue::of(100, 'EUR'), 'en' => null]),
        );
    }

    public function test_sessions_are_a_compound_field_of_a_range_and_a_venue(): void
    {
        $this->assertSame(
            [
                'name' => 'sessions',
                'type' => 'sessions',
                'fields' => [
                    ['name' => 'dates', 'type' => 'daterange', 'settings' => ['time' => true]],
                    ['name' => 'venue', 'type' => 'relation', 'resource' => 'venues'],
                    ['name' => 'tickets', 'type' => 'text'],
                ],
                'multiple' => true,
            ],
            Sessions::make('sessions')->append([Text::make('tickets')])->venues('venues')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'sessions', 'type' => 'sessions', 'fields' => [
                ['name' => 'dates', 'type' => 'daterange', 'settings' => ['time' => true, 'allDay' => true, 'timeZone' => 'America/Toronto']],
            ], 'multiple' => true],
            Sessions::make('sessions')->dates(fn (DateRange $dates) => $dates->allDay()->timeZone('America/Toronto'))->toDefinition(),
        );
        // venues() again replaces the venue
        $definition = Sessions::make('sessions')->venues('venues')->venues('places')->toDefinition();
        $this->assertSame(['dates', 'venue'], array_column($definition['fields'], 'name'));
        $this->assertSame('places', $definition['fields'][1]['resource']);
    }
}
