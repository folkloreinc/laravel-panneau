<?php

namespace Panneau\Fields;

use Closure;
use InvalidArgumentException;
use Panneau\Fields\Concerns\ValidatesEachLocale;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;
use Panneau\Forms\Values;
use Panneau\Rules\PostalCode;

/**
 * A postal address (panneau-js decision 0053): `{ "street", "unit", "city", "region",
 * "postalCode", "country" }`, and `{ "lat", "lng" }` in `point` with `point()`. Empty parts are
 * left out. An address is filled by a street, a unit, a city, a region or a postal code: without
 * one, it is `null`, or its point alone. A country alone is no address.
 *
 * - **Checks.** Once the address is filled, the parts of `requiredParts()` (`street`, `city`
 *   and `country` by default) are required, and the postal code must be one of the address's own
 *   country (the `postalCode` rule, for Canada and the United States). Each error is keyed by
 *   the path of its part: `address.postalCode`. The field's own `required()` requires an
 *   address.
 * - **Saving** trims the parts, puts the country in capitals, or `country()` when the address
 *   has none, the region of Canada and the United States as its code (`Québec` is `QC`), and the
 *   postal code in the format of its country (`H2X 1Y4`).
 * - **Model.** The attribute needs a JSON column with the `array` cast.
 */
class Address extends Field
{
    use ValidatesEachLocale;

    /**
     * The parts of an address, in the order of the value.
     */
    public const PARTS = ['street', 'unit', 'city', 'region', 'postalCode', 'country'];

    /**
     * The parts a filled address must have without `requiredParts()`: those every postal address
     * has.
     */
    public const DEFAULT_REQUIRED_PARTS = ['street', 'city', 'country'];

    /**
     * The parts that fill an address: a country alone, which the admin may set by itself, or a
     * point alone, is no address.
     */
    public const FILLING_PARTS = ['street', 'unit', 'city', 'region', 'postalCode'];

    /**
     * The names of the parts in validation messages.
     */
    private const PART_NAMES = [
        'street' => 'street',
        'unit' => 'unit',
        'city' => 'city',
        'region' => 'region',
        'postalCode' => 'postal code',
        'country' => 'country',
    ];

    public function type(): string
    {
        return 'address';
    }

    /**
     * The country of a new address, as a code (`CA`): the admin puts it in the value as soon as
     * a part is typed, and saving sets it on an address without one.
     */
    public function country(string $code): static
    {
        return $this->settings(['country' => Country::code($code)]);
    }

    /**
     * The countries the field allows, as codes (`['CA', 'US']`): the server refuses the others.
     * With one, the admin doesn't show the country, and sets it.
     *
     * @param  list<string>  $codes
     */
    public function countries(array $codes): static
    {
        return $this->settings(['countries' => Country::codes($codes)]);
    }

    /**
     * The countries the admin shows first, as codes (`['CA', 'US']`).
     *
     * @param  list<string>  $codes
     */
    public function preferred(array $codes): static
    {
        return $this->settings(['preferred' => Country::codes($codes)]);
    }

    /**
     * The parts a filled address must have (`settings.required`), among `Address::PARTS`.
     * Without it: `street`, `city` and `country`.
     *
     * @param  list<string>  $parts
     *
     * @throws InvalidArgumentException For a part that isn't one of an address.
     */
    public function requiredParts(array $parts): static
    {
        foreach ($parts as $part) {
            if (! in_array($part, self::PARTS, true)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a part of an address: %s.', $part, implode(', ', self::PARTS)));
            }
        }

        return $this->settings(['required' => array_values(array_unique($parts))]);
    }

    /**
     * Adds a map under the parts, and the place of the address to the value (`point`).
     */
    public function point(bool $point = true): static
    {
        return $this->settings(['point' => $point]);
    }

    /**
     * The parts a filled address must have.
     *
     * @return list<string>
     */
    public function getRequiredParts(): array
    {
        $parts = $this->settings['required'] ?? null;

        return is_array($parts) ? array_values(array_intersect($parts, self::PARTS)) : self::DEFAULT_REQUIRED_PARTS;
    }

    public function hasPoint(): bool
    {
        return ($this->settings['point'] ?? false) === true;
    }

    /**
     * The codes the field allows: those of `countries()`, or all of them.
     *
     * @return list<string>
     */
    public function allowedCountries(): array
    {
        $countries = $this->settings['countries'] ?? null;

        return is_array($countries) ? array_values(array_filter($countries, Country::isCode(...))) : Country::CODES;
    }

    /**
     * Normalizes an address as saving does: parts trimmed, empty ones left out, the country in
     * capitals (or `$country` for an address without one), the region of Canada and the United
     * States as its code, the postal code in the format of its country, and the point with
     * `$point` only. An address without a street, a unit, a city, a region or a postal code is
     * its point alone, or null: a country alone is no address.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>|null
     */
    public static function normalize(array $value, ?string $country = null, bool $point = false): ?array
    {
        $parts = [];
        foreach (self::PARTS as $part) {
            $text = $value[$part] ?? null;
            $text = is_int($text) ? (string) $text : $text;
            if (is_string($text) && trim($text) !== '') {
                $parts[$part] = trim($text);
            }
        }
        $place = $point ? self::pointOf($value['point'] ?? null) : null;
        // Without a part that fills it, an address is its point, or nothing: never a country alone
        if (array_intersect_key($parts, array_flip(self::FILLING_PARTS)) === []) {
            return $place !== null ? ['point' => $place] : null;
        }
        $parts['country'] = isset($parts['country']) ? strtoupper($parts['country']) : $country;
        if ($parts['country'] === null) {
            unset($parts['country']);
        }
        $country = $parts['country'] ?? null;
        if (isset($parts['region'])) {
            $parts['region'] = Regions::code($parts['region'], $country) ?? $parts['region'];
        }
        if (isset($parts['postalCode'])) {
            $parts['postalCode'] = PostalCode::normalize($parts['postalCode'], $country);
        }
        $normalized = array_intersect_key(array_merge(array_flip(self::PARTS), $parts), $parts);
        if ($place !== null) {
            $normalized['point'] = $place;
        }

        return $normalized;
    }

    protected function valueRules(ValidationContext $context): array
    {
        $rules = ['array'];
        if ($this->isRequired($context->mode)) {
            // An address with nothing but a country (or a point) is no address
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && ! self::isFilled($value)) {
                    $fail(__('The :attribute field is required.'));
                }
            };
        }

        return $rules;
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if (! is_array($value)) {
            return;
        }
        if ($this->hasPoint() && ($value['point'] ?? null) !== null) {
            $rules->add($path.'.point', ['array']);
            $rules->add($path.'.point.lat', ['required', 'numeric', 'between:-90,90']);
            $rules->add($path.'.point.lng', ['required', 'numeric', 'between:-180,180']);
        }
        if (! self::isFilled($value)) {
            return;
        }
        $required = $this->getRequiredParts();
        $allowed = $this->allowedCountries();
        // Saving sets the country of the field on an address without one
        if (is_string($this->settings['country'] ?? null)) {
            $required = array_values(array_diff($required, ['country']));
        }
        foreach (self::PARTS as $part) {
            $partRules = [in_array($part, $required, true) ? 'required' : 'nullable', 'string', 'max:255'];
            if ($part === 'country') {
                $partRules[] = function (string $attribute, mixed $country, Closure $fail) use ($allowed): void {
                    if (is_string($country) && ! in_array(strtoupper(trim($country)), $allowed, true)) {
                        $fail(__('The selected :attribute is invalid.'));
                    }
                };
            }
            if ($part === 'postalCode') {
                $partRules[] = new PostalCode(country: $this->countryOf($value));
            }
            $rules->attribute($path.'.'.$part, __(self::PART_NAMES[$part]));
            $rules->add($path.'.'.$part, $partRules);
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $country = $this->settings['country'] ?? null;

        return self::normalize($value, is_string($country) ? $country : null, $this->hasPoint());
    }

    /**
     * The country of an address: its own, or `country()`.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function countryOf(array $value): ?string
    {
        $country = $value['country'] ?? null;
        if (is_string($country) && trim($country) !== '') {
            return strtoupper(trim($country));
        }
        $default = $this->settings['country'] ?? null;

        return is_string($default) ? $default : null;
    }

    /**
     * Whether an address is filled, as clients count it: a part of `FILLING_PARTS`. A country
     * or a point alone doesn't fill it.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function isFilled(array $value): bool
    {
        foreach (self::FILLING_PARTS as $part) {
            if (Values::filled($value[$part] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function pointOf(mixed $point): ?array
    {
        return is_array($point) && is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)
            ? ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']]
            : null;
    }
}
