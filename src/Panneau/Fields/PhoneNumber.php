<?php

namespace Panneau\Fields;

/**
 * Phone numbers in E.164 (`+15145550199`), the values of `Tel` fields, without a library of
 * numbering plans.
 *
 * A number typed with its calling code (`+33 6…`, `0033 6…`, `011 33 6…`) converts for any
 * country. A number typed without one converts for the countries of `COUNTRIES`, whose national
 * numbers have known lengths: `514 555-0199` in Canada, `06 12 34 56 78` in France. Anything else
 * (a name, a digit too many or too few) can't become E.164 without guessing.
 *
 * An extension follows the number after `;ext=`, as in a `tel:` URI (RFC 3966):
 * `+15145550199;ext=123`, for `Tel` fields with `extension()` (decision 0058 of panneau-js). Methods
 * read and keep extensions only when asked: by default, a number with an extension is not E.164.
 */
final class PhoneNumber
{
    public const E164 = '/^\+[1-9]\d{6,14}$/';

    /** A number in E.164, with an optional extension of 1 to 10 digits: `+15145550199;ext=123`. */
    public const E164_EXTENSION = '/^\+[1-9]\d{6,14}(?:;ext=\d{1,10})?$/';

    /** The longest extension, in digits. */
    public const MAX_EXTENSION_LENGTH = 10;

    /**
     * An extension typed after a number, as people write it: `ext. 123`, `ext:123`, `x123`,
     * `poste 123`, `;ext=123`. The number ends with a digit or a parenthesis.
     */
    private const TYPED_EXTENSION = '/^(.*?[\d)])[\s,;]*(?:extension|ext|poste|x)\s*[.:=#]?\s*(\d{1,10})$/isu';

    /**
     * National numbers that convert without their calling code: the calling code, the trunk
     * prefix dialed before a national number (`0` in France), the national number without it,
     * and how it groups for display (digits per group, the first group joined to the next by a
     * space and the others by `separator`).
     *
     * @var array<string, array{code: string, trunk: string, pattern: string, groups: list<int>, separator: string}>
     */
    public const COUNTRIES = [
        // The North American Numbering Plan: area code and exchange from 2 to 9
        'CA' => ['code' => '1', 'trunk' => '1', 'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/', 'groups' => [3, 3, 4], 'separator' => '-'],
        'US' => ['code' => '1', 'trunk' => '1', 'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/', 'groups' => [3, 3, 4], 'separator' => '-'],
        'FR' => ['code' => '33', 'trunk' => '0', 'pattern' => '/^[1-9]\d{8}$/', 'groups' => [1, 2, 2, 2, 2], 'separator' => ' '],
        'BE' => ['code' => '32', 'trunk' => '0', 'pattern' => '/^[1-9]\d{7,8}$/', 'groups' => [], 'separator' => ' '],
        'CH' => ['code' => '41', 'trunk' => '0', 'pattern' => '/^[1-9]\d{8}$/', 'groups' => [2, 3, 2, 2], 'separator' => ' '],
    ];

    /** The prefix dialed before a calling code: `00`, or `011` from North America. */
    private const EXIT = ['1' => '011'];

    /**
     * Whether a value is a number in E.164: `+15145550199`, or `+15145550199;ext=123` with
     * `$extension`.
     */
    public static function isE164(mixed $value, bool $extension = false): bool
    {
        return is_string($value) && preg_match($extension ? self::E164_EXTENSION : self::E164, $value) === 1;
    }

    /**
     * The number in E.164, or `null` when it doesn't read as one. `$country` is the country of a
     * number typed without its calling code (`CA`).
     *
     * With `$extension`, an extension typed after the number (`514 555-0199 ext. 123`, `x123`,
     * `poste 123`) is kept after `;ext=`: `+15145550199;ext=123`. Without it, a number with an
     * extension doesn't read as one.
     */
    public static function toE164(?string $value, string $country, bool $extension = false): ?string
    {
        $value = trim((string) $value);
        if ($extension && preg_match(self::TYPED_EXTENSION, $value, $match) === 1) {
            $number = self::number(trim($match[1]), $country);

            return $number !== null ? $number.';ext='.$match[2] : null;
        }

        return self::number($value, $country);
    }

    /**
     * The number and the extension of a value: `+15145550199;ext=123` gives `['+15145550199',
     * '123']`. A value without `;ext=` is a number without an extension.
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function splitExtension(?string $value): array
    {
        if ($value !== null && preg_match('/^(.*);ext=(\d{1,10})$/s', $value, $match) === 1) {
            return [$match[1] !== '' ? $match[1] : null, $match[2]];
        }

        return [$value !== '' ? $value : null, null];
    }

    /**
     * The number of a value without its extension, for a service that has none, such as SMS:
     * `+15145550199;ext=123` gives `+15145550199`.
     */
    public static function withoutExtension(?string $value): ?string
    {
        return self::splitExtension($value)[0];
    }

    /**
     * The digits of an extension, written alone (`123`) or with its label (`ext. 123`, `x123`,
     * `poste 123`), or `null` when it doesn't read as one.
     */
    public static function extension(string|int|null $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^(?:(?:extension|ext|poste|x)\s*[.:=#]?\s*)?(\d{1,10})$/iu', $value, $match) === 1
            ? $match[1]
            : null;
    }

    /** A number without an extension, in E.164. */
    private static function number(string $value, string $country): ?string
    {
        // Digits, spaces and the punctuation of phone numbers only: no names, no extensions
        if ($value === '' || preg_match('/[^\d\s().\/+-]/', $value) === 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $value);
        $local = self::COUNTRIES[$country] ?? null;
        $exit = self::EXIT[$local['code'] ?? ''] ?? '00';
        if (str_starts_with($value, '+')) {
            return self::international($digits);
        }
        if (str_starts_with($digits, $exit)) {
            return self::international(substr($digits, strlen($exit)));
        }
        if ($local === null) {
            return null;
        }
        if (str_starts_with($digits, $local['trunk']) && preg_match($local['pattern'], substr($digits, strlen($local['trunk']))) === 1) {
            $digits = substr($digits, strlen($local['trunk']));
        }

        return preg_match($local['pattern'], $digits) === 1 ? '+'.$local['code'].$digits : null;
    }

    /**
     * The number as people read it: in the format of `$country` when it is a number of that
     * country (`514 555-0199`, `06 12 34 56 78`), with its calling code otherwise
     * (`+33 6 12 34 56 78`). A value that isn't E.164 is returned as it is.
     */
    public static function format(?string $value, string $country): ?string
    {
        [$numberCountry, $national] = self::split($value);
        if ($numberCountry === null) {
            return $value;
        }

        $plan = self::COUNTRIES[$numberCountry];
        $grouped = self::group($national, $plan['groups'], $plan['separator']);
        if ((self::COUNTRIES[$country]['code'] ?? null) === $plan['code']) {
            // The trunk prefix of North America isn't dialed within it
            return ($plan['code'] === '1' ? '' : $plan['trunk']).$grouped;
        }

        return '+'.$plan['code'].' '.$grouped;
    }

    /**
     * The national number of an E.164 value of `$country`, without its calling code nor its trunk
     * prefix (`5145550199`), or `null` for a number of another country or a value that isn't
     * E.164.
     */
    public static function nationalNumber(?string $value, string $country): ?string
    {
        [$numberCountry, $national] = self::split($value);

        return $numberCountry !== null && self::COUNTRIES[$numberCountry]['code'] === (self::COUNTRIES[$country]['code'] ?? null)
            ? $national
            : null;
    }

    /** A number after its exit prefix or `+`: E.164 when its country's national number checks. */
    private static function international(string $digits): ?string
    {
        foreach (self::COUNTRIES as $plan) {
            if (! str_starts_with($digits, $plan['code'])) {
                continue;
            }
            $national = substr($digits, strlen($plan['code']));
            // `+33 (0)6…`: the trunk prefix, written in parentheses, isn't dialed from abroad
            if ($plan['trunk'] === '0' && str_starts_with($national, '0')) {
                $national = substr($national, 1);
            }

            return preg_match($plan['pattern'], $national) === 1 ? '+'.$plan['code'].$national : null;
        }

        return self::isE164('+'.$digits) ? '+'.$digits : null;
    }

    /**
     * The country of a table and the national number of an E.164 value, or nulls.
     *
     * @return array{0: string|null, 1: string}
     */
    private static function split(?string $value): array
    {
        if (! self::isE164($value)) {
            return [null, ''];
        }
        foreach (self::COUNTRIES as $country => $plan) {
            $national = substr((string) $value, 1 + strlen($plan['code']));
            if (str_starts_with((string) $value, '+'.$plan['code']) && preg_match($plan['pattern'], $national) === 1) {
                return [$country, $national];
            }
        }

        return [null, ''];
    }

    /** @param  list<int>  $groups */
    private static function group(string $digits, array $groups, string $separator): string
    {
        if ($groups === [] || array_sum($groups) !== strlen($digits)) {
            return $digits;
        }
        $parts = [];
        $offset = 0;
        foreach ($groups as $size) {
            $parts[] = substr($digits, $offset, $size);
            $offset += $size;
        }

        return array_shift($parts).' '.implode($separator, $parts);
    }
}
