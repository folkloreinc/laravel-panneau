<?php

namespace Panneau\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Panneau\Money\Currency;
use Panneau\Money\Money;
use UnexpectedValueException;

/**
 * Keeps the value of a `Money` field in an integer column of its amount, in minor units, and a
 * column of its currency, or a fixed currency (panneau-js decision 0053):
 *
 * ```php
 * 'price' => AsMoney::class.':price_cents,currency',  // the currency in a column
 * 'price' => AsMoney::class.':price_cents,CAD',       // always in Canadian dollars
 * ```
 *
 * The second argument is a currency when it is an ISO 4217 code in capitals, a column otherwise.
 * The attribute is a `Panneau\Money\Money`, or null when the amount is. It takes a `Money`, an
 * array `{ amount, currency }` or null; a fixed currency refuses amounts in another one.
 *
 * Amounts kept in major units (floats, decimals) are converted once with `panneau:convert-money`:
 * the cast refuses to read an amount that isn't an integer.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class AsMoney implements CastsAttributes
{
    /**
     * The fixed currency, or null when it is in a column.
     */
    public readonly ?string $currency;

    /**
     * The column of the currency, or null when it is fixed.
     */
    public readonly ?string $currencyColumn;

    public function __construct(public readonly string $amount, string $currency)
    {
        $this->currency = Currency::isCode($currency) ? $currency : null;
        $this->currencyColumn = $this->currency === null ? $currency : null;
        if ($amount === '' || $currency === '') {
            throw new InvalidArgumentException('AsMoney takes the column of the amount, and the column of the currency or a currency: AsMoney::class.\':price_cents,currency\'.');
        }
    }

    /**
     * The cast that a model declares (`AsMoney::class.':price_cents,CAD'`), or null when it is
     * another cast.
     */
    public static function fromCast(mixed $cast): ?self
    {
        if (! is_string($cast) || ! str_starts_with($cast, self::class.':')) {
            return null;
        }
        $arguments = explode(',', substr($cast, strlen(self::class) + 1));

        return new self($arguments[0], $arguments[1] ?? '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $amount = $attributes[$this->amount] ?? null;
        if ($amount === null) {
            return null;
        }
        if (is_string($amount) && preg_match('/^-?\d+$/', $amount) === 1) {
            $amount = (int) $amount;
        }
        if (! is_int($amount)) {
            throw new UnexpectedValueException(sprintf('The %s column of %s is not an amount in minor units: convert it with panneau:convert-money.', $this->amount, $model::class));
        }
        $currency = $this->currency ?? ($attributes[(string) $this->currencyColumn] ?? null);
        if (! Currency::isCode($currency)) {
            throw new UnexpectedValueException(sprintf('The %s column of %s has an amount without a known currency.', $this->currencyColumn, $model::class));
        }

        return Money::of($amount, $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_array($value)) {
            $amount = $value['amount'] ?? null;
            $currency = $value['currency'] ?? $this->currency;
            if (! is_int($amount) || ! is_string($currency)) {
                throw new InvalidArgumentException(sprintf('The %s attribute takes a Money, { amount, currency } with an integer amount, or null.', $key));
            }
            $value = Money::of($amount, $currency);
        }
        if ($value !== null && ! $value instanceof Money) {
            throw new InvalidArgumentException(sprintf('The %s attribute takes a Money, { amount, currency } with an integer amount, or null.', $key));
        }
        if ($value !== null && $this->currency !== null && $value->currency !== $this->currency) {
            throw new InvalidArgumentException(sprintf('The %s attribute is in %s, not %s.', $key, $this->currency, $value->currency));
        }

        $columns = [$this->amount => $value?->amount];
        if ($this->currencyColumn !== null) {
            $columns[$this->currencyColumn] = $value?->currency;
        }

        return $columns;
    }
}
