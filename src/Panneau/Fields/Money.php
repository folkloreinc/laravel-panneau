<?php

namespace Panneau\Fields;

use Closure;
use Panneau\Forms\ValidationContext;
use Panneau\Money\Currency;
use Panneau\Money\Money as MoneyValue;

/**
 * An amount of money, `{ "amount": 1250, "currency": "CAD" }` (panneau-js decision 0053): an
 * integer in the minor unit of the currency (cents for `CAD`, yen for `JPY`), and an ISO 4217
 * code. The type implies the protocol's `money` rule: the server refuses any other value, a
 * decimal amount, and a currency outside `currency()` or `currencies()`. `min()` and `max()`
 * apply to the amount, in minor units.
 *
 * Keep it in two columns with the `AsMoney` cast, which gives a `Panneau\Money\Money`.
 */
class Money extends Field
{
    public function type(): string
    {
        return 'money';
    }

    /**
     * Fixes the currency (`CAD`): the field shows it, and the server refuses any other.
     */
    public function currency(string $code): static
    {
        return $this->settings(['currency' => Currency::code($code)]);
    }

    /**
     * The currencies the user picks from (`['CAD', 'USD']`), the first one for a new amount: the
     * server refuses the others. Every currency without it, nor `currency()`.
     *
     * @param  list<string>  $codes
     */
    public function currencies(array $codes): static
    {
        return $this->settings(['currencies' => Currency::codes($codes)]);
    }

    /**
     * The currencies the field allows: that of `currency()`, those of `currencies()`, or all of
     * `Currency::CODES`.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $currency = $this->settings['currency'] ?? null;
        if (Currency::isCode($currency)) {
            return [$currency];
        }
        $currencies = $this->settings['currencies'] ?? null;
        $currencies = is_array($currencies) ? array_values(array_filter($currencies, Currency::isCode(...))) : [];

        return $currencies !== [] ? $currencies : Currency::CODES;
    }

    protected function valueRules(ValidationContext $context): array
    {
        return [function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value) || ! array_key_exists('amount', $value)) {
                $fail(__('The :attribute must be an amount with its currency.'));

                return;
            }
            if (self::amount($value['amount']) === null) {
                $fail(__('The amount of the :attribute must be a whole number of the currency\'s minor unit.'));

                return;
            }
            $currency = $value['currency'] ?? null;
            if ($currency === null || $currency === '') {
                $fail(__('The :attribute needs a currency.'));
            } elseif (! in_array($currency, $this->allowed(), true)) {
                $fail(__('The currency of the :attribute is invalid.'));
            }
        }];
    }

    /**
     * `min` and `max` apply to the amount, in minor units, in the currency of the value.
     */
    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        $limit = $rule['value'] ?? null;
        if (! in_array($rule['rule'], ['min', 'max'], true) || ! (is_int($limit) || is_float($limit))) {
            return parent::toLaravelRule($rule, $prefix);
        }
        $min = $rule['rule'] === 'min';
        $message = is_string($rule['message'] ?? null) ? $rule['message'] : null;

        return [$rule['rule'], function (string $attribute, mixed $value, Closure $fail) use ($limit, $min, $message): void {
            if (! is_array($value)) {
                return;
            }
            $amount = self::amount($value['amount'] ?? null);
            if ($amount === null || ($min ? $amount >= $limit : $amount <= $limit)) {
                return;
            }
            $currency = Currency::isCode($value['currency'] ?? null) ? $value['currency'] : null;
            $shown = $currency !== null && is_int($limit) ? MoneyValue::of($limit, $currency)->toDecimal().' '.$currency : (string) $limit;
            $fail($message ?? ($min
                ? __('The :attribute must be at least :min.', ['min' => $shown])
                : __('The :attribute must not be greater than :max.', ['max' => $shown])));
        }];
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return ['amount' => self::amount($value['amount'] ?? null), 'currency' => $value['currency'] ?? null];
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value instanceof MoneyValue ? $value->toArray() : parent::serializeValue($value);
    }

    /**
     * An amount in minor units: an integer, or a float without decimals, which JSON gives for
     * `1250.0`. Null for anything else.
     */
    private static function amount(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount;
        }
        if (is_float($amount) && is_finite($amount) && floor($amount) === $amount && abs($amount) <= 2 ** 53) {
            return (int) $amount;
        }

        return null;
    }

    protected function sortsByValue(): bool
    {
        return true;
    }
}
