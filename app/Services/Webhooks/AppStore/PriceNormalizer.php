<?php

namespace App\Services\Webhooks\AppStore;

use App\Exceptions\InvalidProviderMoneyException;
use App\Support\Money\Money;
use InvalidArgumentException;

/**
 * Turns App Store transaction money into our canonical {@see Money} — the one
 * place that knows Apple's units, so nothing past the webhook boundary does.
 *
 * Apple's `price` is an int64 in milliunits of `currency` (1 unit = 1000
 * milliunits) whatever the currency's own precision: USD 1.99 → 1990,
 * JPY 300 → 300000, KRW 3300 → 3300000. It is the price recorded on the
 * purchase, after any offer discount. `currency` is ISO 4217 and only present
 * alongside `price` (it says nothing about the storefront — that's
 * `storefront`). A partial refund is expressed as `revocationPercentage`, in
 * milliunits of a percent (100000 = the whole transaction).
 *
 * Money that can't be trusted — a negative or fractional price, one of the
 * pair without the other, an unsupported currency, a percentage out of range —
 * throws {@see InvalidProviderMoneyException} rather than being guessed at.
 * No price at all is not an error: plenty of transactions carry none.
 *
 * Rounding happens once: the price converts exactly into the currency's minor
 * unit (real Apple prices are always whole minor units), then a refund share
 * is rounded once from the exact product. Rounding to milliunits first would
 * round twice and can be off by one minor unit.
 *
 * Apple notes these figures aren't for revenue reconciliation — they're the
 * gross customer price; App Store Connect reports remain the record of
 * proceeds.
 *
 * @see https://developer.apple.com/documentation/appstoreservernotifications/price
 * @see https://developer.apple.com/documentation/appstoreservernotifications/revocationpercentage
 */
final class PriceNormalizer
{
    /** Apple's `price` carries three implied decimal places. */
    private const int MILLIUNIT_DECIMALS = 3;

    /** `revocationPercentage` for a full refund. */
    private const int FULL_REVOCATION = 100000;

    /**
     * What the customer was charged in this transaction, or null when Apple
     * sent no price.
     *
     * @param  array<string, mixed>|null  $transactionInfo  Decoded signedTransactionInfo.
     *
     * @throws InvalidProviderMoneyException
     */
    public static function charged(?array $transactionInfo): ?Money
    {
        $price = $transactionInfo['price'] ?? null;
        $currency = $transactionInfo['currency'] ?? null;
        $currency = $currency === '' ? null : $currency;

        if ($price === null && $currency === null) {
            return null;
        }

        if ($price === null || $currency === null) {
            throw new InvalidProviderMoneyException('App Store price and currency must arrive together.');
        }

        if (! is_int($price) && ! (is_string($price) && preg_match('/^-?\d+$/', $price))) {
            throw new InvalidProviderMoneyException("App Store price [{$price}] is not a whole number of milliunits.");
        }

        if ((int) $price < 0) {
            throw new InvalidProviderMoneyException("App Store price [{$price}] is negative.");
        }

        if (! is_string($currency)) {
            throw new InvalidProviderMoneyException('App Store currency is not a string.');
        }

        try {
            return Money::ofScaled((int) $price, self::MILLIUNIT_DECIMALS, $currency);
        } catch (InvalidArgumentException $e) {
            throw new InvalidProviderMoneyException($e->getMessage(), previous: $e);
        }
    }

    /**
     * How much of the transaction has been refunded so far — the charged
     * price scaled by `revocationPercentage` when Apple sends one, the full
     * price otherwise.
     *
     * @param  array<string, mixed>|null  $transactionInfo  Decoded signedTransactionInfo of the refunded transaction.
     *
     * @throws InvalidProviderMoneyException
     */
    public static function refunded(?array $transactionInfo): ?Money
    {
        $charged = self::charged($transactionInfo);
        $percentage = $transactionInfo['revocationPercentage'] ?? null;

        if (! $charged || $percentage === null) {
            return $charged;
        }

        if (! is_int($percentage) || $percentage < 0 || $percentage > self::FULL_REVOCATION) {
            throw new InvalidProviderMoneyException('App Store revocationPercentage ['.json_encode($percentage).'] is outside 0–'.self::FULL_REVOCATION.'.');
        }

        return $charged->multipliedBy($percentage, self::FULL_REVOCATION);
    }
}
