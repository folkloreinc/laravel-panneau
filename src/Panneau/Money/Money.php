<?php

namespace Panneau\Money;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * An amount of money: an integer in the minor unit of its currency (cents for `CAD`), and an
 * ISO 4217 currency, as the protocol's `money` values (panneau-js decision 0053). The value of
 * the `AsMoney` cast; `Money` fields serialize it as `{ "amount", "currency" }`.
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class Money implements Arrayable, JsonSerializable
{
    /**
     * @throws InvalidArgumentException When the currency isn't a known ISO 4217 code.
     */
    public function __construct(public int $amount, public string $currency)
    {
        Currency::code($currency);
    }

    /**
     * An amount in minor units: `Money::of(1250, 'CAD')` is 12.50 $.
     */
    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * An amount in major units, as typed or kept by a site (`12.5`, `'12.50'`), rounded to the
     * minor unit of the currency, half away from zero, without the errors of floating point:
     * `fromDecimal(1.005, 'CAD')` is 101 cents, `fromDecimal('12.5', 'JPY')` 13 yen.
     *
     * @throws InvalidArgumentException When the amount isn't a number, or is too large.
     */
    public static function fromDecimal(string|float|int $major, string $currency): self
    {
        $digits = Currency::digits(Currency::code($currency));
        if (is_float($major) && ! is_finite($major)) {
            throw new InvalidArgumentException('An amount must be a finite number.');
        }
        // A float as its shortest representation, as JavaScript's String() gives it
        $text = is_string($major) ? trim($major) : var_export($major, true);
        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $text, $match) !== 1
            || ($match[2] === '' && ($match[3] ?? '') === '')) {
            throw new InvalidArgumentException(sprintf('%s is not an amount.', var_export($major, true)));
        }
        $negative = $match[1] === '-';
        $number = $match[2].($match[3] ?? '');
        $point = strlen($match[2]) + (int) ($match[4] ?? 0) + $digits;

        if ($point <= 0) {
            [$whole, $rest] = ['0', str_repeat('0', -$point).$number];
        } elseif ($point >= strlen($number)) {
            [$whole, $rest] = [$number.str_repeat('0', $point - strlen($number)), ''];
        } else {
            [$whole, $rest] = [substr($number, 0, $point), substr($number, $point)];
        }
        $whole = ltrim($whole, '0');
        if (strlen($whole) > 18) {
            throw new InvalidArgumentException(sprintf('%s is too large an amount.', var_export($major, true)));
        }
        $amount = (int) $whole + ($rest !== '' && $rest[0] >= '5' ? 1 : 0);

        return new self($negative ? -$amount : $amount, $currency);
    }

    /**
     * The number of digits of the minor unit of the currency.
     */
    public function digits(): int
    {
        return Currency::digits($this->currency);
    }

    /**
     * The amount in major units, with the digits of the currency: `'12.50'` for 1250 cents,
     * `'1250'` for 1250 yen. A string, so that no floating point error creeps in.
     */
    public function toDecimal(): string
    {
        $digits = $this->digits();
        $text = str_pad((string) abs($this->amount), $digits + 1, '0', STR_PAD_LEFT);
        $decimal = $digits > 0 ? substr($text, 0, -$digits).'.'.substr($text, -$digits) : $text;

        return ($this->amount < 0 ? '-' : '').$decimal;
    }

    /**
     * The amount in the currency format of a locale: `1 250,00 $` in `fr_CA`, `CA$1,250.00` in
     * `en_US`, with the digits of the currency in ISO 4217. Without the `intl` extension, the
     * amount and the code: `1250.00 CAD`.
     */
    public function format(string $locale): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $this->digits());
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $this->digits());
            $formatted = $formatter->formatCurrency((float) $this->toDecimal(), $this->currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $this->toDecimal().' '.$this->currency;
    }

    /**
     * Whether two amounts are the same: the same amount in the same currency.
     */
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
