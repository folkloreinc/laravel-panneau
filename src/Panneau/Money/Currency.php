<?php

namespace Panneau\Money;

use InvalidArgumentException;

/**
 * The ISO 4217 currencies of `money` values (panneau-js decision 0053), and the digits of their
 * minor unit. The same table as `CURRENCY_CODES` and `getCurrencyDigits()` of `@panneau/protocol`:
 * keep them identical.
 */
final class Currency
{
    /**
     * The ISO 4217 codes of the currencies a `money` value can have: the currencies in use, and a
     * few withdrawn recently, which stored amounts may still have. Funds, precious metals and
     * test codes are left out.
     */
    public const CODES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT',
        'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD',
        'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP',
        'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF',
        'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK',
        'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK',
        'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU',
        'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR',
        'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR',
        'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'SSP', 'STN', 'SVC',
        'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX',
        'USD', 'UYU', 'UZS', 'VED', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XCG', 'XOF', 'XPF',
        'YER', 'ZAR', 'ZMW', 'ZWG', 'ZWL',
    ];

    /**
     * The currencies whose minor unit isn't a hundredth, by their exponent in ISO 4217. The CLDR,
     * which `NumberFormatter` follows, differs for a few cash currencies: amounts follow ISO 4217.
     */
    public const EXPONENTS = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0,
        'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    /**
     * Whether a value is the ISO 4217 code of a currency that Panneau knows, in capitals (`CAD`).
     */
    public static function isCode(mixed $code): bool
    {
        return is_string($code) && in_array($code, self::CODES, true);
    }

    /**
     * Checks a code.
     *
     * @throws InvalidArgumentException When it isn't a known ISO 4217 code in capitals.
     */
    public static function code(mixed $code): string
    {
        if (! is_string($code) || ! self::isCode($code)) {
            throw new InvalidArgumentException(sprintf('%s is not an ISO 4217 currency code, in capitals.', var_export($code, true)));
        }

        return $code;
    }

    /**
     * Checks a list of codes.
     *
     * @param  array<array-key, mixed>  $codes
     * @return list<string>
     *
     * @throws InvalidArgumentException When one isn't a known ISO 4217 code in capitals.
     */
    public static function codes(array $codes): array
    {
        return array_values(array_map(self::code(...), $codes));
    }

    /**
     * The number of digits of the minor unit of a currency, its ISO 4217 exponent: 2 for `CAD`
     * (cents), 0 for `JPY`, 3 for `KWD`. A `money` amount is an integer in that unit.
     */
    public static function digits(string $code): int
    {
        return self::EXPONENTS[$code] ?? 2;
    }
}
