<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * ISO 4217 minor-unit exponents — how many decimal digits a currency's
 * smallest unit has (USD 2 → cents, JPY 0, KWD 3). This is what turns a
 * `amount_minor` integer back into a real amount, so it must follow ISO 4217:
 * only currencies listed here are supported, and an unknown code is rejected
 * rather than assumed to have two decimals — guessing wrong would scale an
 * amount by a power of ten without anyone noticing.
 */
final class Currency
{
    /** @var array<string, int> currencies whose exponent isn't 2 */
    private const array EXPONENTS = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    /**
     * Every other active ISO 4217 currency (exponent 2). Precious metals,
     * SDRs and testing codes (XAU, XDR, XTS, XXX …) are deliberately absent.
     *
     * @var list<string>
     */
    private const array TWO_DECIMALS = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN',
        'BMD', 'BND', 'BOB', 'BOV', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHE', 'CHF',
        'CHW', 'CNY', 'COP', 'COU', 'CRC', 'CUP', 'CVE', 'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB',
        'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GTQ', 'GYD', 'HKD', 'HNL', 'HTG', 'HUF',
        'IDR', 'ILS', 'INR', 'IRR', 'JMD', 'KES', 'KGS', 'KHR', 'KPW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR',
        'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN',
        'MXV', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR',
        'PLN', 'QAR', 'RON', 'RSD', 'RUB', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SOS',
        'SRD', 'SSP', 'STN', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS',
        'UAH', 'USD', 'USN', 'UYU', 'UZS', 'VED', 'VES', 'WST', 'XCD', 'XCG', 'YER', 'ZAR', 'ZMW', 'ZWG',
    ];

    /** Minor-unit exponent of a supported currency; throws for anything else. */
    public static function exponent(string $currency): int
    {
        $code = self::normalize($currency);

        if (isset(self::EXPONENTS[$code])) {
            return self::EXPONENTS[$code];
        }

        if (in_array($code, self::TWO_DECIMALS, true)) {
            return 2;
        }

        throw new InvalidArgumentException("Unsupported currency [{$code}] — not an ISO 4217 currency this app knows the exponent of.");
    }

    /** Whether a code is a well-formed, supported ISO 4217 currency. */
    public static function isSupported(string $currency): bool
    {
        $code = strtoupper(trim($currency));

        return isset(self::EXPONENTS[$code]) || in_array($code, self::TWO_DECIMALS, true);
    }

    /** Upper-cases and validates a three-letter currency code (format only — see {@see self::exponent()}). */
    public static function normalize(string $currency): string
    {
        $code = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currency}].");
        }

        return $code;
    }

    /** A sum of minor units as a float in major units — for display/aggregates only, never for storage. */
    public static function toMajor(int|float|string $minor, string $currency): float
    {
        $exponent = self::exponent($currency);

        return round((float) $minor / (10 ** $exponent), $exponent);
    }
}
