<?php

namespace App\Support\Money;

use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * An exact amount of one currency, held as an integer count of that
 * currency's minor unit ({@see Currency::exponent()}): USD 9.99 is 999,
 * JPY 300 is 300, KWD 1.250 is 1250. This is the canonical shape money is
 * stored in (`amount_minor` + `currency`).
 *
 * Provider integrations convert their own units exactly once, at the
 * boundary, with {@see self::ofScaled()} — e.g. Apple milliunits are
 * `ofScaled($price, 3, ...)`, Google micros `ofScaled($micros, 6, ...)` —
 * so nothing past that point knows any provider's unit.
 */
final readonly class Money
{
    public string $currency;

    public function __construct(public int $minor, string $currency)
    {
        $this->currency = Currency::normalize($currency);

        // Fails fast on a currency with no known exponent, so no amount ever exists in one.
        Currency::exponent($this->currency);
    }

    public static function ofMinor(int $minor, string $currency): self
    {
        return new self($minor, $currency);
    }

    /**
     * An integer amount carrying `$decimals` implied fractional digits
     * (milliunits = 3, micros = 6, minor units = the currency's exponent),
     * rescaled to the currency's minor unit with integer arithmetic only. If
     * the source is more precise than the currency (never the case for real
     * store prices) it is rounded half away from zero.
     */
    public static function ofScaled(int|string $value, int $decimals, string $currency): self
    {
        if (is_string($value) && ! preg_match('/^-?\d+$/', $value)) {
            throw new InvalidArgumentException("Scaled amount [{$value}] must be an integer.");
        }

        $value = (int) $value;
        $shift = Currency::exponent($currency) - $decimals;

        if ($shift >= 0) {
            return new self($value * (10 ** $shift), $currency);
        }

        $divisor = 10 ** -$shift;
        $half = intdiv($divisor, 2) * ($value < 0 ? -1 : 1);

        return new self(intdiv($value + $half, $divisor), $currency);
    }

    /** A decimal amount in major units ("9.99", "300") parsed exactly, without floats. */
    public static function ofMajor(string|int $amount, string $currency): self
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim((string) $amount), $parts)) {
            throw new InvalidArgumentException("Amount [{$amount}] is not a decimal number.");
        }

        $fraction = $parts[3] ?? '';

        return self::ofScaled($parts[1].$parts[2].$fraction, strlen($fraction), $currency);
    }

    /** Major units as an exact decimal string with the currency's own precision ("9.99", "300", "1.250"). */
    public function toDecimal(): string
    {
        $exponent = Currency::exponent($this->currency);
        $digits = str_pad((string) abs($this->minor), $exponent + 1, '0', STR_PAD_LEFT);
        $sign = $this->minor < 0 ? '-' : '';

        return $exponent === 0
            ? $sign.$digits
            : $sign.substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }

    public function toFloat(): float
    {
        return Currency::toMajor($this->minor, $this->currency);
    }

    /** Localised display ("$9.99", "PKR 4,900.00", "¥300"). */
    public function format(?string $locale = null): string
    {
        return (string) Number::currency($this->toFloat(), in: $this->currency, locale: $locale ?? app()->getLocale());
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /** `$this` × numerator / denominator, rounded half away from zero (e.g. a partial-refund percentage). */
    public function multipliedBy(int $numerator, int $denominator): self
    {
        $product = $this->minor * $numerator;
        $half = intdiv($denominator, 2) * ($product < 0 ? -1 : 1);

        return new self(intdiv($product + $half, $denominator), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
