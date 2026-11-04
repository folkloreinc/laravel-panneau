<?php

namespace Panneau\Tests\Unit\Money;

use InvalidArgumentException;
use Panneau\Money\Currency;
use Panneau\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_the_currencies_and_their_digits(): void
    {
        // The table of @panneau/protocol: CURRENCY_CODES and getCurrencyDigits()
        $this->assertCount(161, Currency::CODES);
        $this->assertSame(Currency::CODES, array_values(array_unique(Currency::CODES)));
        $sorted = Currency::CODES;
        sort($sorted);
        $this->assertSame($sorted, Currency::CODES);
        foreach (Currency::CODES as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
        }
        $this->assertSame(2, Currency::digits('CAD'));
        $this->assertSame(0, Currency::digits('JPY'));
        $this->assertSame(3, Currency::digits('KWD'));
        $this->assertSame(4, Currency::digits('CLF'));
        $this->assertSame(
            ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'],
            array_keys(array_filter(Currency::EXPONENTS, fn (int $digits): bool => $digits === 0)),
        );
        $this->assertSame(['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'], array_keys(array_filter(Currency::EXPONENTS, fn (int $digits): bool => $digits === 3)));
        $this->assertTrue(Currency::isCode('EUR'));
        $this->assertFalse(Currency::isCode('eur'));
        $this->assertFalse(Currency::isCode('XAU'));
        $this->assertSame(['CAD', 'USD'], Currency::codes(['CAD', 'USD']));
    }

    /**
     * @return array<string, array{string|float|int, string, int}>
     */
    public static function decimals(): array
    {
        return [
            'dollars' => [12.5, 'CAD', 1250],
            'a string with its zeros' => ['12.50', 'CAD', 1250],
            'an integer' => [12, 'CAD', 1200],
            'half a cent, up' => [1.005, 'CAD', 101],
            'half a cent, away from zero' => [-1.005, 'CAD', -101],
            'below half a cent' => ['0.004', 'CAD', 0],
            'a float sum' => [0.1 + 0.2, 'CAD', 30],
            'yen' => ['12.5', 'JPY', 13],
            'dinars' => ['1.2345', 'KWD', 1235],
            'an exponent' => ['1e3', 'CAD', 100000],
            'a small exponent' => [1.0E-7, 'CAD', 0],
            'no leading digit' => ['.5', 'CAD', 50],
            'spaces' => [' 3 ', 'CAD', 300],
        ];
    }

    #[DataProvider('decimals')]
    public function test_amounts_in_major_units_are_rounded_to_the_minor_unit(string|float|int $major, string $currency, int $amount): void
    {
        $this->assertSame($amount, Money::fromDecimal($major, $currency)->amount);
    }

    /**
     * @return array<string, array{string|float|int, string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'text' => ['12,50 $', 'CAD'],
            'empty' => ['', 'CAD'],
            'a point' => ['.', 'CAD'],
            'infinite' => [INF, 'CAD'],
            'too large' => ['1e20', 'CAD'],
            'an unknown currency' => [12, 'XXX'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_throw(string|float|int $major, string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal($major, $currency);
    }

    public function test_a_currency_must_be_known(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(100, 'cad');
    }

    public function test_decimals_equality_and_json(): void
    {
        $this->assertSame('12.50', Money::of(1250, 'CAD')->toDecimal());
        $this->assertSame('-0.05', Money::of(-5, 'CAD')->toDecimal());
        $this->assertSame('0.00', Money::of(0, 'EUR')->toDecimal());
        $this->assertSame('1250', Money::of(1250, 'JPY')->toDecimal());
        $this->assertSame('1.250', Money::of(1250, 'KWD')->toDecimal());

        $this->assertTrue(Money::of(1250, 'CAD')->equals(Money::fromDecimal('12.5', 'CAD')));
        $this->assertFalse(Money::of(1250, 'CAD')->equals(Money::of(1250, 'USD')));
        $this->assertFalse(Money::of(1250, 'CAD')->equals(Money::of(1251, 'CAD')));

        $this->assertSame(['amount' => 1250, 'currency' => 'CAD'], Money::of(1250, 'CAD')->toArray());
        $this->assertSame('{"amount":1250,"currency":"CAD"}', json_encode(Money::of(1250, 'CAD')));
    }

    #[RequiresPhpExtension('intl')]
    public function test_amounts_are_formatted_in_the_currency_format_of_a_locale(): void
    {
        $this->assertSame("1\u{a0}250,00\u{a0}\$", Money::of(125000, 'CAD')->format('fr_CA'));
        $this->assertSame('$1,250.00', Money::of(125000, 'USD')->format('en_US'));
        $this->assertSame('CA$1,250.00', Money::of(125000, 'CAD')->format('en_US'));
        $this->assertSame('¥1,250', Money::of(1250, 'JPY')->format('en_US'));
        // The digits of ISO 4217, not those of the CLDR
        $this->assertStringContainsString('1.500', Money::of(1500, 'IQD')->format('en_US'));
    }
}
