<?php

namespace Panneau\Tests\Unit\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Panneau\Casts\AsDateRange;
use Panneau\Casts\AsMoney;
use Panneau\Money\Money;
use Panneau\Tests\TestCase;
use UnexpectedValueException;

class CastsTest extends TestCase
{
    private function event(): Model
    {
        return new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'dates' => AsDateRange::class.':starts_at,ends_at',
                    'period' => AsDateRange::columns('period_start', 'period_end', timeZone: 'zone', allDay: 'all_day'),
                    'price' => AsMoney::class.':price_cents,currency',
                    'fee' => AsMoney::class.':fee_cents,CAD',
                ];
            }
        };
    }

    public function test_a_range_is_kept_in_two_columns_in_the_time_zone_of_the_app(): void
    {
        $this->app['config']->set('app.timezone', 'America/Toronto');
        $event = $this->event();

        $event->dates = ['start' => '2026-11-04T23:30:00Z', 'end' => '2026-11-05T01:00:00+00:00'];
        $this->assertSame(['starts_at' => '2026-11-04 18:30:00', 'ends_at' => '2026-11-04 20:00:00'], $event->getAttributes());
        $this->assertInstanceOf(CarbonImmutable::class, $event->dates['start']);
        $this->assertSame('2026-11-04T18:30:00-05:00', $event->dates['start']->toAtomString());
        $this->assertSame(['start', 'end'], array_keys($event->dates));

        // Days at their midnight, an open end
        $event->dates = ['start' => '2026-11-04', 'end' => null];
        $this->assertSame(['starts_at' => '2026-11-04 00:00:00', 'ends_at' => null], $event->getAttributes());
        $this->assertNull($event->dates['end']);

        $event->dates = null;
        $this->assertNull($event->dates);
        $this->assertSame(['starts_at' => null, 'ends_at' => null], $event->getAttributes());
    }

    public function test_a_range_can_keep_its_zone_and_all_day(): void
    {
        $event = $this->event();

        $event->period = ['start' => '2026-11-04', 'end' => '2026-11-06', 'timeZone' => 'America/Vancouver', 'allDay' => true];
        $this->assertSame(
            ['period_start' => '2026-11-04 00:00:00', 'period_end' => '2026-11-06 00:00:00', 'zone' => 'America/Vancouver', 'all_day' => true],
            $event->getAttributes(),
        );
        $this->assertSame('America/Vancouver', $event->period['timeZone']);
        $this->assertTrue($event->period['allDay']);

        $event->period = ['start' => '2026-11-04T10:00:00Z', 'end' => '2026-11-04T12:00:00Z'];
        $this->assertNull($event->period['timeZone']);
        $this->assertFalse($event->period['allDay']);
    }

    public function test_the_cast_of_a_range_is_read_from_its_declaration(): void
    {
        $this->assertSame(AsDateRange::class.':starts_at,ends_at', AsDateRange::columns('starts_at', 'ends_at'));
        $this->assertSame(AsDateRange::class.':a,b,,all_day', AsDateRange::columns('a', 'b', allDay: 'all_day'));
        $range = AsDateRange::fromCast(AsDateRange::class.':a,b,,all_day');
        $this->assertNotNull($range);
        $this->assertSame(['a', 'b', null, 'all_day'], [$range->start, $range->end, $range->timeZone, $range->allDay]);
        $this->assertSame('starts_at', AsDateRange::fromCast(AsDateRange::class)?->start);
        $this->assertNull(AsDateRange::fromCast('datetime'));
        $this->assertNull(AsDateRange::fromCast(AsDateRange::class.'Other:a,b'));
    }

    public function test_a_range_must_be_an_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event()->dates = '2026-11-04';
    }

    public function test_money_is_kept_as_an_amount_and_a_currency(): void
    {
        $event = $this->event();

        $event->price = Money::of(1250, 'USD');
        $this->assertSame(['price_cents' => 1250, 'currency' => 'USD'], $event->getAttributes());
        $this->assertTrue(Money::of(1250, 'USD')->equals($event->price));

        $event->price = ['amount' => 500, 'currency' => 'EUR'];
        $this->assertSame(['price_cents' => 500, 'currency' => 'EUR'], $event->getAttributes());

        // A fixed currency
        $event->fee = ['amount' => 300, 'currency' => 'CAD'];
        $this->assertSame(300, $event->getAttributes()['fee_cents']);
        $this->assertArrayNotHasKey('CAD', $event->getAttributes());
        $this->assertTrue(Money::of(300, 'CAD')->equals($event->fee));

        $event->price = null;
        $this->assertNull($event->price);
        $this->assertNull($event->getAttributes()['currency']);

        // Integers as strings, as some drivers give them
        $event->setRawAttributes(['price_cents' => '-75', 'currency' => 'CAD']);
        $this->assertSame(-75, $event->price?->amount);
    }

    public function test_the_cast_of_money_is_read_from_its_declaration(): void
    {
        $column = AsMoney::fromCast(AsMoney::class.':price_cents,currency');
        $fixed = AsMoney::fromCast(AsMoney::class.':price_cents,CAD');
        $this->assertSame(['price_cents', null, 'currency'], [$column?->amount, $column?->currency, $column?->currencyColumn]);
        $this->assertSame(['price_cents', 'CAD', null], [$fixed?->amount, $fixed?->currency, $fixed?->currencyColumn]);
        $this->assertNull(AsMoney::fromCast('integer'));
    }

    public function test_a_fixed_currency_refuses_another_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event()->fee = Money::of(100, 'USD');
    }

    public function test_money_refuses_decimal_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event()->price = ['amount' => 12.5, 'currency' => 'CAD'];
    }

    public function test_amounts_not_converted_are_not_read(): void
    {
        $event = $this->event();
        $event->setRawAttributes(['price_cents' => '12.50', 'currency' => 'CAD']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('panneau:convert-money');
        $event->price;
    }

    public function test_amounts_need_a_currency(): void
    {
        $event = $this->event();
        $event->setRawAttributes(['price_cents' => 1250, 'currency' => null]);

        $this->expectException(UnexpectedValueException::class);
        $event->price;
    }
}
