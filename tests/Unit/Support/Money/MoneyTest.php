<?php

namespace Tests\Unit\Support\Money;

use App\Exceptions\InvalidProviderMoneyException;
use App\Services\Webhooks\AppStore\PriceNormalizer;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_currency_exponents_follow_iso_4217(): void
    {
        $this->assertSame(2, Currency::exponent('USD'));
        $this->assertSame(2, Currency::exponent('pkr'));
        $this->assertSame(0, Currency::exponent('JPY'));
        $this->assertSame(0, Currency::exponent('KRW'));
        $this->assertSame(3, Currency::exponent('KWD'));
        $this->assertSame(4, Currency::exponent('CLF'));
    }

    public function test_only_iso_4217_currencies_are_supported(): void
    {
        $this->assertTrue(Currency::isSupported('usd'));
        $this->assertTrue(Currency::isSupported('PKR'));
        $this->assertTrue(Currency::isSupported('XCG'));
        $this->assertFalse(Currency::isSupported('ZZZ'));
        $this->assertFalse(Currency::isSupported('US'));
    }

    public function test_an_unsupported_currency_never_falls_back_to_two_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Currency::exponent('ZZZ');
    }

    public function test_money_cannot_be_created_in_an_unsupported_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::ofMinor(100, 'ZZZ');
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidApplePrices(): array
    {
        return [
            'negative price' => [['price' => -1990, 'currency' => 'USD']],
            'fractional price' => [['price' => '1990.5', 'currency' => 'USD']],
            'non-numeric price' => [['price' => 'free', 'currency' => 'USD']],
            'price without currency' => [['price' => 1990]],
            'currency without price' => [['currency' => 'USD']],
            'unsupported currency' => [['price' => 1990, 'currency' => 'ZZZ']],
            'malformed currency' => [['price' => 1990, 'currency' => 'US']],
        ];
    }

    #[DataProvider('invalidApplePrices')]
    public function test_apple_money_that_cannot_be_trusted_is_rejected_at_the_boundary(array $info): void
    {
        $this->expectException(InvalidProviderMoneyException::class);

        PriceNormalizer::charged($info);
    }

    public function test_an_apple_transaction_with_no_price_at_all_has_no_amount(): void
    {
        $this->assertNull(PriceNormalizer::charged([]));
        $this->assertNull(PriceNormalizer::charged(null));
    }

    public function test_a_revocation_percentage_outside_apples_range_is_rejected(): void
    {
        $this->expectException(InvalidProviderMoneyException::class);

        PriceNormalizer::refunded(['price' => 9990, 'currency' => 'USD', 'revocationPercentage' => 100001]);
    }

    public function test_an_invalid_currency_code_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::ofMinor(100, 'US');
    }

    /** @return array<string, array{int|string, int, string, int}> */
    public static function scaledAmounts(): array
    {
        return [
            'Apple milliunits → USD cents' => [9990, 3, 'USD', 999],
            'Apple milliunits → JPY yen' => [300000, 3, 'JPY', 300],
            'Apple milliunits → KWD fils' => [1250, 3, 'KWD', 1250],
            'Google micros → INR paise' => [999000000, 6, 'INR', 99900],
            'Google micros → JPY yen' => ['480000000', 6, 'JPY', 480],
            'minor units unchanged' => [999, 2, 'EUR', 999],
            'fewer decimals scale up' => [5, 0, 'USD', 500],
            'excess precision rounds half up' => [9995, 3, 'USD', 1000],
            'excess precision rounds half away from zero' => [-9995, 3, 'USD', -1000],
        ];
    }

    #[DataProvider('scaledAmounts')]
    public function test_scaled_provider_units_convert_exactly(int|string $value, int $decimals, string $currency, int $minor): void
    {
        $this->assertSame($minor, Money::ofScaled($value, $decimals, $currency)->minor);
    }

    public function test_major_amounts_parse_without_floats(): void
    {
        $this->assertSame(999, Money::ofMajor('9.99', 'USD')->minor);
        $this->assertSame(300, Money::ofMajor('300', 'JPY')->minor);
        $this->assertSame(300, Money::ofMajor('300.00', 'JPY')->minor);
        $this->assertSame(1250, Money::ofMajor('1.25', 'KWD')->minor);
        $this->assertSame(1000000000001, Money::ofMajor('10000000000.01', 'USD')->minor);
    }

    public function test_to_decimal_uses_the_currencys_precision(): void
    {
        $this->assertSame('9.99', Money::ofMinor(999, 'USD')->toDecimal());
        $this->assertSame('0.05', Money::ofMinor(5, 'USD')->toDecimal());
        $this->assertSame('-4.99', Money::ofMinor(-499, 'USD')->toDecimal());
        $this->assertSame('300', Money::ofMinor(300, 'JPY')->toDecimal());
        $this->assertSame('1.250', Money::ofMinor(1250, 'KWD')->toDecimal());
    }

    public function test_arithmetic_refuses_to_mix_currencies(): void
    {
        $this->assertSame(1998, Money::ofMinor(999, 'USD')->plus(Money::ofMinor(999, 'USD'))->minor);
        $this->assertSame(500, Money::ofMinor(999, 'USD')->multipliedBy(50000, 100000)->minor);

        $this->expectException(InvalidArgumentException::class);

        Money::ofMinor(999, 'USD')->plus(Money::ofMinor(999, 'EUR'));
    }

    public function test_apple_prices_and_refund_percentages_normalise_at_the_boundary(): void
    {
        $charged = PriceNormalizer::charged(['price' => 4900000, 'currency' => 'PKR']);
        $this->assertSame([490000, 'PKR'], [$charged->minor, $charged->currency]);

        $this->assertSame(999, PriceNormalizer::refunded(['price' => 9990, 'currency' => 'USD'])->minor, 'no percentage = full refund');
        $this->assertSame(250, PriceNormalizer::refunded(['price' => 9990, 'currency' => 'USD', 'revocationPercentage' => 25000])->minor);
    }
}
