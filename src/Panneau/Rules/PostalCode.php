<?php

namespace Panneau\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The protocol's `postalCode` rule (panneau-js decision 0053): a postal code must normalize to
 * the format of its country, for the countries whose format is known (Canada and the United
 * States). Postal codes of other countries, or without a country, pass.
 *
 * The country is a code (`new PostalCode('CA')`), or the value of another field of the form, at
 * its dot path in the data being validated (`new PostalCode(countryPath: 'country')`).
 */
final class PostalCode implements DataAwareRule, ValidationRule
{
    /**
     * Canadian postal codes, normalized: `H2X 1Y4`. No D, F, I, O, Q or U, and no W or Z first.
     */
    public const CA = '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z] \d[ABCEGHJ-NPRSTV-Z]\d$/';

    /**
     * ZIP codes, normalized: `12345`, or ZIP+4 `12345-6789`.
     */
    public const US = '/^\d{5}(-\d{4})?$/';

    /**
     * The formats the rule checks, by country.
     */
    public const FORMATS = ['CA' => self::CA, 'US' => self::US];

    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly ?string $country = null,
        private readonly ?string $countryPath = null,
    ) {}

    /**
     * Normalizes a postal code for its country, as clients do: `H2X 1Y4` for Canada (`h2x1y4`),
     * `12345` or `12345-6789` for the United States (`123456789`, `12345 6789`). Elsewhere, or
     * when it doesn't read as one of its country, the code in capitals, its spaces trimmed and
     * collapsed.
     */
    public static function normalize(string $code, ?string $country): string
    {
        $compact = strtoupper((string) preg_replace('/[\s-]+/u', '', $code));
        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === 'CA' && preg_match('/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $compact) === 1) {
            return substr($compact, 0, 3).' '.substr($compact, 3);
        }
        if ($country === 'US' && preg_match('/^\d{5}$/', $compact) === 1) {
            return $compact;
        }
        if ($country === 'US' && preg_match('/^\d{9}$/', $compact) === 1) {
            return substr($compact, 0, 5).'-'.substr($compact, 5);
        }

        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($code)));
    }

    /**
     * Whether a postal code, once normalized, has the format of its country. Codes of the
     * countries whose format isn't known, or without a country, are valid.
     */
    public static function isValid(string $code, ?string $country): bool
    {
        $country = $country !== null ? strtoupper(trim($country)) : null;
        $format = $country !== null ? (self::FORMATS[$country] ?? null) : null;

        return $format === null || preg_match($format, self::normalize($code, $country)) === 1;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Other rules refuse values that aren't text
        if (! is_string($value) && ! is_int($value)) {
            return;
        }
        $country = $this->country;
        if ($country === null && $this->countryPath !== null) {
            $field = data_get($this->data, $this->countryPath);
            $country = is_string($field) && trim($field) !== '' ? $field : null;
        }
        if (! self::isValid((string) $value, $country)) {
            $fail(__('The :attribute is not a valid postal code.'));
        }
    }
}
