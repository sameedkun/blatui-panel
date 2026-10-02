<?php

namespace Tests\Feature\Services\Subscription;

use App\Enum\AppleNotificationSubtype as Subtype;
use App\Enum\AppleNotificationType as Type;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionSource;
use App\Enum\SubscriptionStatus;
use App\Enum\TransactionType;
use App\Mail\Billing\PaymentFailedMail;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\Webhooks\AppleNotification;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Services\Account\DeletionService;
use App\Services\Webhooks\AppStore\NotificationProcessor;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\Concerns\SignsAppStoreNotifications;
use Tests\TestCase;

class AppStoreNotificationProcessorTest extends TestCase
{
    use RefreshDatabase, SignsAppStoreNotifications;

    private const MONTHLY = 'com.example.app.pro.monthly';

    private const YEARLY = 'com.example.app.pro.yearly';

    private User $user;

    private PlanPrice $monthly;

    private PlanPrice $yearly;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.app_store.environments' => ['Production']]);

        $this->user = User::factory()->app()->create();
        $this->monthly = $this->mappedPrice(self::MONTHLY, 9.99);
        $this->yearly = $this->mappedPrice(self::YEARLY, 99.99);
    }

    private function mappedPrice(string $productId, float $amount): PlanPrice
    {
        $price = PlanPrice::factory()->create(['amount' => $amount]);
        PlanPriceProvider::factory()->create(['plan_price_id' => $price->id, 'provider' => PaymentProvider::AppStore, 'external_id' => $productId]);

        return $price;
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>  $renewal
     */
    private function process(Type $type, ?Subtype $subtype = null, array $transaction = [], array $renewal = [], ?CarbonInterface $signedAt = null): AppleNotification
    {
        $transaction = $this->appStoreTransaction(['appAccountToken' => $this->user->appAccountToken(), ...$transaction]);

        $notification = AppleNotification::factory()->create([
            'notification_type' => $type,
            'subtype' => $subtype,
            'signed_date' => $signedAt ?? now(),
            'payload' => ['data' => ['environment' => 'Production']],
            'transaction_info' => $transaction,
            'renewal_info' => $this->appStoreRenewal($renewal),
            'app_account_token' => $transaction['appAccountToken'],
            'original_transaction_id' => $transaction['originalTransactionId'],
            'transaction_id' => $transaction['transactionId'],
            'product_id' => $transaction['productId'],
        ]);

        app(NotificationProcessor::class)->process($notification);

        return $notification->refresh();
    }

    private function subscribe(array $transaction = []): Subscription
    {
        $this->process(Type::Subscribed, Subtype::InitialBuy, $transaction);

        return $this->user->subscriptions()->latest('id')->firstOrFail();
    }

    /** @return list<TransactionType> */
    private function transactionTypes(Subscription $subscription): array
    {
        return $subscription->transactions()->orderBy('id')->pluck('type')->all();
    }

    /** Captures everything written to log channels (the processor and ledger log to `webhooks`). */
    private function spyOnLogs(): LoggerInterface&MockInterface
    {
        $logger = Mockery::spy(LoggerInterface::class);
        Log::partialMock()->shouldReceive('channel')->andReturn($logger);

        return $logger;
    }

    /** @return list<int|null> amounts (minor units) of this row's ledger entries of one type, oldest first */
    private function amounts(Subscription $subscription, TransactionType $type): array
    {
        return $subscription->transactions()->where('type', $type)->orderBy('id')->pluck('amount_minor')->all();
    }

    /** @return array<string, int> currency => net minor units collected on the row */
    private function netPaid(Subscription $subscription): array
    {
        return collect($subscription->fresh()->load('transactions')->netPaid())->map->minor->all();
    }

    public function test_a_free_trial_starts_as_trialing_with_nothing_paid(): void
    {
        $subscription = $this->subscribe(['offerType' => 1, 'offerDiscountType' => 'FREE_TRIAL', 'price' => 0, 'expiresDate' => now()->addWeek()->getTimestampMs()]);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->equalTo($subscription->ends_at));
        $this->assertSame(['USD' => 0], $this->netPaid($subscription));
        $this->assertSame(SubscriptionSource::Purchase, $subscription->source);
        $this->assertTrue($this->user->fresh()->isOnTrial());
    }

    public function test_a_new_purchase_replaces_an_existing_local_subscription(): void
    {
        $local = Subscription::factory()->create(['user_id' => $this->user->id]);

        $subscription = $this->subscribe();

        $this->assertSame(SubscriptionStatus::Cancelled, $local->refresh()->status);
        $this->assertSame(CancelledBy::System, $local->cancelled_by);
        $this->assertTrue($local->ends_at->lessThanOrEqualTo(now()));
        $this->assertSame($subscription->id, $this->user->activeSubscription()->first()->id);
    }

    public function test_a_purchase_for_an_unknown_account_is_left_unprocessed(): void
    {
        $notification = $this->process(Type::Subscribed, Subtype::InitialBuy, ['appAccountToken' => '00000000-0000-0000-0000-000000000000']);

        $this->assertFalse($notification->processed);
        $this->assertSame(0, Subscription::count());
    }

    public function test_an_unmapped_product_is_left_unprocessed_until_mapped_and_reprocessed(): void
    {
        $notification = $this->process(Type::Subscribed, Subtype::InitialBuy, ['productId' => 'com.example.app.new']);

        $this->assertFalse($notification->processed);
        $this->assertSame(0, Subscription::count());

        $price = $this->mappedPrice('com.example.app.new', 4.99);
        app(NotificationProcessor::class)->process($notification);

        $this->assertTrue($notification->refresh()->processed);
        $this->assertSame($price->id, $this->user->activeSubscription()->first()->plan_price_id);
    }

    public function test_a_renewal_extends_the_same_contract_and_is_idempotent(): void
    {
        $subscription = $this->subscribe();
        $renewedUntil = now()->addMonths(2)->startOfSecond();

        $renewal = ['transactionId' => '2000000000000002', 'expiresDate' => $renewedUntil->getTimestampMs()];
        $notification = $this->process(Type::DidRenew, null, $renewal);
        app(NotificationProcessor::class)->process($notification);

        $subscription->refresh();
        $this->assertTrue($notification->processed);
        $this->assertTrue($subscription->ends_at->equalTo($renewedUntil));
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame([TransactionType::Initial, TransactionType::Renewal], $this->transactionTypes($subscription));
        $this->assertSame(1, $this->user->subscriptions()->count());
    }

    public function test_a_renewal_after_a_free_trial_converts_it_and_records_the_first_charge(): void
    {
        $subscription = $this->subscribe(['offerType' => 1, 'offerDiscountType' => 'FREE_TRIAL', 'price' => 0, 'expiresDate' => now()->addWeek()->getTimestampMs()]);

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'expiresDate' => now()->addWeek()->addMonth()->getTimestampMs()]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(['USD' => 999], $this->netPaid($subscription));
    }

    public function test_a_renewal_onto_another_product_chains_a_new_subscription(): void
    {
        $original = $this->subscribe();

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);

        $current = $this->user->activeSubscription()->first();
        $this->assertSame($this->yearly->id, $current->plan_price_id);
        $this->assertSame($original->id, $current->previous_subscription_id);
        $this->assertSame(['USD' => 9999], $this->netPaid($current));
        $this->assertSame([TransactionType::PlanChange], $this->transactionTypes($current));
        $this->assertSame(SubscriptionStatus::Cancelled, $original->refresh()->status);
    }

    public function test_an_upgrade_applies_immediately_and_a_downgrade_waits_for_renewal(): void
    {
        $this->subscribe();

        $this->process(Type::DidChangeRenewalPref, Subtype::Downgrade, renewal: ['autoRenewProductId' => self::YEARLY]);
        $this->assertSame($this->monthly->id, $this->user->activeSubscription()->first()->plan_price_id);

        $this->process(Type::DidChangeRenewalPref, Subtype::Upgrade, ['transactionId' => '2000000000000003', 'productId' => self::YEARLY]);
        $this->assertSame($this->yearly->id, $this->user->activeSubscription()->first()->plan_price_id);
        $this->assertSame(2, $this->user->subscriptions()->count());
    }

    public function test_turning_auto_renew_off_keeps_access_until_period_end_and_back_on_restores_it(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewDisabled, renewal: ['autoRenewStatus' => 0]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame(CancelledBy::User, $subscription->cancelled_by);
        $this->assertFalse($subscription->is_recurring);
        $this->assertTrue($this->user->fresh()->isSubscribed());

        $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewEnabled);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->cancelled_by);
        $this->assertTrue($subscription->is_recurring);
        // Toggling auto-renew moves no money, so the ledger only holds the purchase.
        $this->assertSame([TransactionType::Initial], $this->transactionTypes($subscription));
    }

    public function test_a_billing_failure_with_grace_keeps_access_and_without_grace_stops_it(): void
    {
        $subscription = $this->subscribe(['expiresDate' => now()->subHour()->getTimestampMs(), 'purchaseDate' => now()->subMonth()->getTimestampMs()]);
        $graceEndsAt = now()->addDays(6)->startOfSecond();

        $this->process(Type::DidFailToRenew, Subtype::GracePeriod, renewal: ['gracePeriodExpiresDate' => $graceEndsAt->getTimestampMs(), 'isInBillingRetryPeriod' => true]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo($graceEndsAt));
        $this->assertTrue($this->user->fresh()->isSubscribed());

        $this->process(Type::GracePeriodExpired);

        $this->assertSame(SubscriptionStatus::Failed, $subscription->refresh()->status);
        $this->assertFalse($this->user->fresh()->isSubscribed());
    }

    public function test_billing_recovery_reactivates_a_failed_subscription(): void
    {
        $subscription = $this->subscribe(['expiresDate' => now()->subHour()->getTimestampMs()]);
        $this->process(Type::DidFailToRenew);
        $this->assertSame(SubscriptionStatus::Failed, $subscription->refresh()->status);

        $this->process(Type::DidRenew, Subtype::BillingRecovery, ['transactionId' => '2000000000000002', 'expiresDate' => now()->addMonth()->getTimestampMs()]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertTrue($this->user->fresh()->isSubscribed());
    }

    public function test_voluntary_expiry_ends_the_contract_as_user_cancelled(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Expired, Subtype::Voluntary, ['expiresDate' => now()->subMinute()->getTimestampMs()]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertSame(CancelledBy::User, $subscription->cancelled_by);
        $this->assertFalse($this->user->fresh()->isSubscribed());
    }

    public function test_refunding_the_current_period_revokes_access_and_a_reversal_restores_it(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationReason' => 1]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('Refunded: Issue with the app', $subscription->cancelled_reason);
        $this->assertFalse($this->user->fresh()->isSubscribed());

        $this->process(Type::RefundReversed);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertTrue($this->user->fresh()->isSubscribed());
        $this->assertSame([TransactionType::Initial, TransactionType::Refund, TransactionType::RefundReversed], $this->transactionTypes($subscription));
        $this->assertSame(['USD' => 999], $this->netPaid($subscription));
    }

    public function test_refunding_an_earlier_period_only_records_the_refund(): void
    {
        $subscription = $this->subscribe(['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs()]);
        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'expiresDate' => now()->addMonth()->getTimestampMs()]);

        $this->process(Type::Refund, null, ['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs(), 'revocationReason' => 0]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertContains(TransactionType::Refund, $this->transactionTypes($subscription));
    }

    public function test_a_renewal_extension_pushes_the_end_date_out(): void
    {
        $subscription = $this->subscribe();
        $extendedTo = now()->addMonths(3)->startOfSecond();

        $this->process(Type::RenewalExtended, null, ['expiresDate' => $extendedTo->getTimestampMs()]);

        $this->assertTrue($subscription->refresh()->ends_at->equalTo($extendedTo));
    }

    public function test_a_family_sharing_revoke_ends_access_now(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Revoke);

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
        $this->assertFalse($this->user->fresh()->isSubscribed());
    }

    public function test_an_out_of_order_state_change_older_than_an_applied_one_is_skipped(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewEnabled, signedAt: now());
        $stale = $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewDisabled, signedAt: now()->subMinute());

        $this->assertTrue($stale->processed);
        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_a_state_change_for_an_unknown_contract_is_left_unprocessed(): void
    {
        $notification = $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewDisabled);

        $this->assertFalse($notification->processed);
    }

    public function test_notifications_without_a_token_resolve_the_owner_through_the_contract(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'appAccountToken' => null, 'expiresDate' => now()->addMonths(2)->getTimestampMs()]);

        $this->assertSame([TransactionType::Initial, TransactionType::Renewal], $this->transactionTypes($subscription));
    }

    public function test_a_renewal_whose_purchase_was_never_seen_starts_the_contract(): void
    {
        $notification = $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000005']);

        $this->assertTrue($notification->processed);
        $this->assertSame($this->monthly->id, $this->user->activeSubscription()->first()->plan_price_id);
    }

    public function test_informational_notifications_are_marked_processed_without_changes(): void
    {
        foreach ([Type::Test, Type::RefundDeclined, Type::ConsumptionRequest] as $type) {
            $this->assertTrue($this->process($type)->processed);
        }

        $this->assertSame(0, Subscription::count());
    }

    public function test_net_paid_follows_renewals_refunds_and_reversals(): void
    {
        $subscription = $this->subscribe();
        $renewal = ['transactionId' => '2000000000000002', 'expiresDate' => now()->addMonths(2)->getTimestampMs()];

        $notification = $this->process(Type::DidRenew, null, $renewal);
        app(NotificationProcessor::class)->process($notification);
        $this->assertSame(['USD' => 1998], $this->netPaid($subscription));

        $this->process(Type::Refund, null, [...$renewal, 'revocationDate' => now()->getTimestampMs()]);
        $this->assertSame(['USD' => 999], $this->netPaid($subscription));

        $this->process(Type::RefundReversed, null, $renewal);
        $this->assertSame(['USD' => 1998], $this->netPaid($subscription));
    }

    public function test_each_charge_keeps_its_own_currency(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'price' => 8990, 'currency' => 'EUR', 'storefront' => 'DEU', 'expiresDate' => now()->addMonths(2)->getTimestampMs()]);

        $this->assertSame(['USD' => 999, 'EUR' => 899], $this->netPaid($subscription));
    }

    public function test_a_refund_is_recorded_on_the_row_that_took_the_charge(): void
    {
        $original = $this->subscribe(['transactionId' => '2000000000000001']);
        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);
        $current = $this->user->activeSubscription()->first();

        $this->process(Type::Refund, null, ['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs()]);

        $this->assertSame(['USD' => 0], $this->netPaid($original));
        $this->assertSame(['USD' => 9999], $this->netPaid($current));
    }

    /**
     * Apple sends `price` in milliunits whatever the currency's precision;
     * the ledger stores the currency's own minor unit.
     *
     * @return array<string, array{int, string, int}>
     */
    public static function applePrices(): array
    {
        return [
            'PKR 4,900 (real sandbox payload)' => [4900000, 'PKR', 490000],
            'GBP 2.99 (real sandbox payload)' => [2990, 'GBP', 299],
            'USD 1.99' => [1990, 'USD', 199],
            'JPY 300 — no minor unit' => [300000, 'JPY', 300],
            'KRW 3,300 — no minor unit' => [3300000, 'KRW', 3300],
            'KWD 1.250 — three decimals' => [1250, 'KWD', 1250],
        ];
    }

    #[DataProvider('applePrices')]
    public function test_apple_milliunit_prices_are_stored_in_the_currencys_minor_unit(int $price, string $currency, int $minor): void
    {
        $subscription = $this->subscribe(['price' => $price, 'currency' => $currency]);

        $transaction = $subscription->transactions()->sole();
        $this->assertSame($minor, $transaction->amount_minor);
        $this->assertSame($currency, $transaction->currency);
        $this->assertSame($price, $transaction->payload['transaction']['price'], 'the raw provider value is kept for audit');
    }

    public function test_a_partial_refund_records_only_the_refunded_share(): void
    {
        $subscription = $this->subscribe();
        $revokedAt = now()->addDay()->startOfSecond();

        $this->process(Type::Refund, null, ['revocationDate' => $revokedAt->getTimestampMs(), 'revocationPercentage' => 50000, 'revocationType' => 'REFUND_PRORATED']);

        $refund = $subscription->transactions()->where('type', TransactionType::Refund)->sole();
        $this->assertSame(500, $refund->amount_minor, '50% of USD 9.99, rounded half up');
        $this->assertTrue($refund->purchased_at->equalTo($revokedAt), 'a refund is dated when the money went back');
        $this->assertSame(['USD' => 499], $this->netPaid($subscription));
    }

    public function test_a_transaction_links_to_the_internal_notification_row_and_keeps_apples_ids_apart(): void
    {
        $this->subscribe();
        $notification = AppleNotification::query()->sole();

        $transaction = SubscriptionTransaction::query()->sole();
        $this->assertSame($notification->id, $transaction->notification_id, 'our apple_notifications.id, not the notificationUUID');
        $this->assertSame('2000000000000001', $transaction->provider_transaction_id);
        $this->assertSame('2000000000000001', $transaction->provider_original_id);
        $this->assertTrue($notification->is($transaction->notification()));
    }

    public function test_the_same_charge_named_by_two_notifications_is_recorded_once(): void
    {
        $subscription = $this->subscribe();

        // A second, distinct notification (new notificationUUID) about the same transaction id.
        $this->process(Type::Subscribed, Subtype::Resubscribe);

        $this->assertSame(2, AppleNotification::count());
        $this->assertSame([TransactionType::Initial], $this->transactionTypes($subscription));
    }

    // ── Refunds & reversals ──────────────────────────────────────────────────

    public function test_a_second_partial_refund_records_only_the_additional_amount(): void
    {
        $subscription = $this->subscribe();

        // Apple reports the share of the transaction refunded so far: 25%, then 75% in total.
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000, 'revocationType' => 'REFUND_PRORATED']);
        $this->process(Type::Refund, null, ['revocationDate' => now()->addDay()->getTimestampMs(), 'revocationPercentage' => 75000, 'revocationType' => 'REFUND_PRORATED']);

        // 9.99 × 25% = 2.4975 → 2.50; 9.99 × 75% = 7.4925 → 7.49, of which 2.50 was already refunded.
        $this->assertSame([250, 499], $this->amounts($subscription, TransactionType::Refund));
        $this->assertSame(['USD' => 250], $this->netPaid($subscription));
    }

    public function test_a_redelivered_or_reprocessed_refund_is_recorded_once(): void
    {
        $subscription = $this->subscribe();
        $refund = ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000];

        $notification = $this->process(Type::Refund, null, $refund);
        app(NotificationProcessor::class)->process($notification); // admin "Reprocess"
        $this->process(Type::Refund, null, $refund);                 // same refund, another notification

        $this->assertSame([250], $this->amounts($subscription, TransactionType::Refund));
    }

    public function test_an_older_partial_refund_arriving_late_records_nothing(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Refund, null, ['revocationDate' => now()->addDay()->getTimestampMs(), 'revocationPercentage' => 75000]);
        $late = $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000]);

        $this->assertTrue($late->processed);
        $this->assertSame([749], $this->amounts($subscription, TransactionType::Refund));
    }

    public function test_a_refund_never_exceeds_the_recorded_charge(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Refund, null, ['price' => 19990, 'revocationDate' => now()->getTimestampMs()]);

        $this->assertSame([999], $this->amounts($subscription, TransactionType::Refund));
        $this->assertSame(['USD' => 0], $this->netPaid($subscription));
    }

    public function test_a_refund_after_a_reversal_is_recorded_and_revokes_access_again(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationReason' => 0]);
        $this->process(Type::RefundReversed, signedAt: now()->addMinute());
        $this->assertTrue($this->user->fresh()->isSubscribed());

        $this->travel(1)->hours();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationReason' => 1]);

        $this->assertSame([999, 999], $this->amounts($subscription, TransactionType::Refund));
        $this->assertSame([999], $this->amounts($subscription, TransactionType::RefundReversed));
        $this->assertSame(['USD' => 0], $this->netPaid($subscription));
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
        $this->assertFalse($this->user->fresh()->isSubscribed());
    }

    public function test_a_reversal_reverses_each_outstanding_refund_exactly_once_and_references_it(): void
    {
        $subscription = $this->subscribe();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000]);
        $this->process(Type::Refund, null, ['revocationDate' => now()->addDay()->getTimestampMs(), 'revocationPercentage' => 75000]);

        $reversal = $this->process(Type::RefundReversed, signedAt: now()->addDays(2));
        app(NotificationProcessor::class)->process($reversal); // replay

        $refunds = $subscription->transactions()->where('type', TransactionType::Refund)->orderBy('id')->get();
        $reversals = $subscription->transactions()->where('type', TransactionType::RefundReversed)->orderBy('id')->get();

        $this->assertTrue($reversal->processed);
        $this->assertSame($refunds->pluck('id')->all(), $reversals->pluck('related_transaction_id')->all());
        $this->assertSame($refunds->pluck('amount_minor')->all(), $reversals->pluck('amount_minor')->all());
        $this->assertSame(['USD' => 999], $this->netPaid($subscription));
    }

    public function test_a_reversal_leaves_a_refund_issued_after_it_alone(): void
    {
        $subscription = $this->subscribe();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000]);
        $this->process(Type::Refund, null, ['revocationDate' => now()->addDays(2)->getTimestampMs(), 'revocationPercentage' => 75000]);

        $this->process(Type::RefundReversed, signedAt: now()->addDay());

        $first = $subscription->transactions()->where('type', TransactionType::Refund)->orderBy('id')->first();
        $reversal = $subscription->transactions()->where('type', TransactionType::RefundReversed)->sole();
        $this->assertSame([$first->id, 250], [$reversal->related_transaction_id, $reversal->amount_minor]);
    }

    public function test_a_reversal_with_no_recorded_refund_is_left_unprocessed(): void
    {
        $subscription = $this->subscribe();

        $notification = $this->process(Type::RefundReversed);

        $this->assertFalse($notification->processed);
        $this->assertSame([TransactionType::Initial], $this->transactionTypes($subscription));
    }

    public function test_only_a_refund_total_lower_than_already_recorded_is_logged_for_review(): void
    {
        $subscription = $this->subscribe();
        $logs = $this->spyOnLogs();

        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 25000]);
        $this->process(Type::Refund, null, ['revocationDate' => now()->addDay()->getTimestampMs(), 'revocationPercentage' => 75000]);
        $logs->shouldNotHaveReceived('warning');

        // Lower than the 75% already on the ledger, with no reversal in between.
        $this->process(Type::Refund, null, ['revocationDate' => now()->addDays(2)->getTimestampMs(), 'revocationPercentage' => 40000]);

        $logs->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'lower than already recorded')
            && $context['transaction_id'] === '2000000000000001'
            && $context['reported_total'] === '4.00'
            && $context['recorded_total'] === '7.49');
        $this->assertSame([250, 499], $this->amounts($subscription, TransactionType::Refund), 'the ledger is not changed');
    }

    public function test_refund_declined_after_a_recorded_refund_is_logged_and_changes_nothing(): void
    {
        $subscription = $this->subscribe();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs()]);
        $logs = $this->spyOnLogs();

        $declined = $this->process(Type::RefundDeclined);

        $this->assertTrue($declined->processed);
        $logs->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'REFUND_DECLINED after a recorded refund')
            && $context['transaction_id'] === '2000000000000001');
        $this->assertSame([999], $this->amounts($subscription, TransactionType::Refund));
    }

    public function test_refund_declined_with_no_outstanding_refund_is_not_logged(): void
    {
        $this->subscribe();
        $logs = $this->spyOnLogs();

        $this->process(Type::RefundDeclined);

        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs()]);
        $this->process(Type::RefundReversed, signedAt: now()->addMinute());
        $this->process(Type::RefundDeclined);

        $logs->shouldNotHaveReceived('warning');
    }

    public function test_a_pay_up_front_charge_records_how_many_billing_periods_it_covers(): void
    {
        // A three-month pay-up-front offer on the monthly product.
        $subscription = $this->subscribe(['offerDiscountType' => 'PAY_UP_FRONT', 'price' => 24990, 'expiresDate' => now()->addDays(90)->getTimestampMs()]);

        $this->assertSame(3, $subscription->transactions()->sole()->periods_covered);
    }

    public function test_a_refund_links_to_the_charge_it_refunds(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs()]);

        $charge = $subscription->transactions()->where('type', TransactionType::Initial)->sole();
        $this->assertSame($charge->id, $subscription->transactions()->where('type', TransactionType::Refund)->sole()->related_transaction_id);
    }

    // ── Phase 3B: renewals after the owner was purged ───────────────────────

    public function test_a_renewal_after_the_owner_was_purged_is_recorded_ownerless_and_flagged(): void
    {
        $subscription = $this->subscribe();
        app(DeletionService::class)->purge($this->user, 'admin', causer: null);
        $logs = $this->spyOnLogs();

        $renewal = $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'appAccountToken' => null, 'expiresDate' => now()->addMonths(2)->getTimestampMs()]);

        $this->assertTrue($renewal->processed);
        $this->assertNull($subscription->refresh()->user_id);
        $this->assertSame([TransactionType::Initial, TransactionType::Renewal], $this->transactionTypes($subscription));
        $logs->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'owner was purged')
            && $context['subscription_id'] === $subscription->id
            && $context['transaction_id'] === '2000000000000002');
    }

    public function test_a_plan_change_at_renewal_after_the_owner_was_purged_stays_ownerless(): void
    {
        $subscription = $this->subscribe();
        app(DeletionService::class)->purge($this->user, 'admin', causer: null);

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'appAccountToken' => null, 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);

        $next = Subscription::query()->where('previous_subscription_id', $subscription->id)->sole();
        $this->assertNull($next->user_id);
        $this->assertSame([TransactionType::PlanChange], $this->transactionTypes($next));
    }

    // ── Phase 3A: plan changes, unusable money, rounding ────────────────────

    public function test_refunding_a_superseded_charge_never_cancels_the_current_subscription(): void
    {
        // A yearly contract upgraded to a monthly higher tier: the old charge's period outlives the new one.
        $old = $this->subscribe(['productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);
        $this->process(Type::DidChangeRenewalPref, Subtype::Upgrade, ['transactionId' => '2000000000000002', 'productId' => self::MONTHLY, 'expiresDate' => now()->addMonth()->getTimestampMs()]);
        $current = $this->user->activeSubscription()->first();

        $this->process(Type::Refund, null, ['transactionId' => '2000000000000001', 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs(), 'revocationDate' => now()->getTimestampMs()]);

        $this->assertSame(SubscriptionStatus::Active, $current->refresh()->status);
        $this->assertTrue($this->user->fresh()->isSubscribed());
        $this->assertSame([9999], $this->amounts($old, TransactionType::Refund), 'the refund is still recorded against the old charge');
    }

    public function test_a_renewal_onto_an_unmapped_product_is_left_unprocessed_until_mapped(): void
    {
        $subscription = $this->subscribe();
        $endsAt = $subscription->ends_at;

        $renewal = $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'productId' => 'com.example.app.pro.weekly', 'expiresDate' => now()->addMonths(2)->getTimestampMs()]);

        $this->assertFalse($renewal->processed);
        $this->assertSame([TransactionType::Initial], $this->transactionTypes($subscription));
        $this->assertTrue($subscription->refresh()->ends_at->equalTo($endsAt), 'the old plan is not renewed with another product\'s charge');

        $weekly = $this->mappedPrice('com.example.app.pro.weekly', 2.99);
        app(NotificationProcessor::class)->process($renewal);

        $this->assertTrue($renewal->refresh()->processed);
        $this->assertSame($weekly->id, $this->user->activeSubscription()->first()->plan_price_id);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unusableMoney(): array
    {
        return [
            'malformed currency' => [['currency' => 'US']],
            'unsupported currency' => [['currency' => 'ZZZ']],
            'negative price' => [['price' => -9990]],
            'fractional price' => [['price' => '9990.5']],
            'price without a currency' => [['currency' => null]],
        ];
    }

    #[DataProvider('unusableMoney')]
    public function test_a_charge_with_unusable_money_is_left_unprocessed_without_failing(array $money): void
    {
        $logs = $this->spyOnLogs();

        $notification = $this->process(Type::Subscribed, Subtype::InitialBuy, $money);

        $this->assertFalse($notification->processed);
        $this->assertSame(0, Subscription::count());
        $this->assertSame(0, SubscriptionTransaction::count());
        $logs->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'unusable price'));
    }

    #[DataProvider('unusableMoney')]
    public function test_state_only_notifications_apply_whatever_the_money_fields_contain(array $money): void
    {
        $subscription = $this->subscribe();

        $off = $this->process(Type::DidChangeRenewalStatus, Subtype::AutoRenewDisabled, $money, ['autoRenewStatus' => 0]);
        $this->assertTrue($off->processed);
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);

        $expired = $this->process(Type::Expired, Subtype::Voluntary, [...$money, 'expiresDate' => now()->subMinute()->getTimestampMs()]);
        $this->assertTrue($expired->processed);
        $this->assertSame(SubscriptionStatus::Expired, $subscription->refresh()->status);
    }

    public function test_a_refund_reversal_does_not_depend_on_the_notifications_money_fields(): void
    {
        $subscription = $this->subscribe();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs()]);

        $reversal = $this->process(Type::RefundReversed, null, ['currency' => 'ZZZ'], signedAt: now()->addMinute());

        $this->assertTrue($reversal->processed);
        $this->assertSame([999], $this->amounts($subscription, TransactionType::RefundReversed));
    }

    public function test_a_refund_percentage_outside_apples_range_is_left_unprocessed(): void
    {
        $subscription = $this->subscribe();

        $refund = $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 150000]);

        $this->assertFalse($refund->processed);
        $this->assertSame([], $this->amounts($subscription, TransactionType::Refund));
    }

    /**
     * Successive cumulative partial refunds in 0- and 3-decimal currencies: each
     * step records target − already refunded, so the steps sum to exactly the
     * final rounded target.
     *
     * @return array<string, array{int, string, list<int>, list<int>}>
     */
    public static function partialRefundsByCurrency(): array
    {
        return [
            // 300 × 33.333% = 99.999 → 100; × 66.667% = 200.001 → 200 (+100).
            'JPY, no minor unit' => [300000, 'JPY', [33333, 66667], [100, 100]],
            // 3300 × 12.345% = 407.385 → 407; × 100% = 3300 (+2893).
            'KRW, no minor unit' => [3300000, 'KRW', [12345, 100000], [407, 2893]],
            // 1.250 × 33.333% = 0.4166625 → 0.417; × 50% = 0.625 (+0.208).
            'KWD, three decimals' => [1250, 'KWD', [33333, 50000], [417, 208]],
            // 4,900.00 × 10% = 490.00; × 25.5% = 1,249.50 (+759.50).
            'PKR, two decimals' => [4900000, 'PKR', [10000, 25500], [49000, 75950]],
        ];
    }

    /**
     * @param  list<int>  $percentages
     * @param  list<int>  $expected
     */
    #[DataProvider('partialRefundsByCurrency')]
    public function test_successive_partial_refunds_round_exactly_per_currency(int $price, string $currency, array $percentages, array $expected): void
    {
        $subscription = $this->subscribe(['price' => $price, 'currency' => $currency]);

        foreach ($percentages as $i => $percentage) {
            $this->process(Type::Refund, null, ['price' => $price, 'currency' => $currency, 'revocationDate' => now()->addDays($i)->getTimestampMs(), 'revocationPercentage' => $percentage]);
        }

        $this->assertSame($expected, $this->amounts($subscription, TransactionType::Refund));
    }

    public function test_a_partial_refund_is_rounded_once_from_the_exact_value(): void
    {
        // USD 9.99 × 0.05% = 0.4995¢ exactly → 0¢. Rounding to milliunits first
        // (4.995 → 5 milli = 0.5¢ → 1¢) would invent a cent. Zero moved, so nothing is recorded.
        $once = $this->subscribe();
        $this->process(Type::Refund, null, ['revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 50]);
        $this->assertSame([], $this->amounts($once, TransactionType::Refund));

        // An exact half rounds away from zero: USD 10.00 × 0.05% = 0.5¢ → 1¢.
        $this->user = User::factory()->app()->create();
        $tie = $this->subscribe(['transactionId' => '2000000000000077', 'originalTransactionId' => '2000000000000077', 'price' => 10000]);
        $this->process(Type::Refund, null, ['transactionId' => '2000000000000077', 'originalTransactionId' => '2000000000000077', 'price' => 10000, 'revocationDate' => now()->getTimestampMs(), 'revocationPercentage' => 50]);
        $this->assertSame([1], $this->amounts($tie, TransactionType::Refund));
    }

    public function test_a_refund_of_a_charge_never_recorded_uses_the_refund_payload_uncapped(): void
    {
        $subscription = $this->subscribe();

        // A renewal from before the integration existed is refunded.
        $this->process(Type::Refund, null, ['transactionId' => '2000000000000009', 'price' => 19990, 'revocationDate' => now()->getTimestampMs()]);

        $refund = $subscription->transactions()->where('type', TransactionType::Refund)->sole();
        $this->assertSame(1999, $refund->amount_minor);
        $this->assertNull($refund->related_transaction_id);
    }

    public function test_a_failed_renewal_emails_the_user_once_per_state_change(): void
    {
        Notification::fake();
        $this->subscribe(['expiresDate' => now()->subHour()->getTimestampMs()]);
        $graceEndsAt = now()->addDays(6)->startOfSecond();

        $failure = $this->process(Type::DidFailToRenew, Subtype::GracePeriod, renewal: ['gracePeriodExpiresDate' => $graceEndsAt->getTimestampMs()]);
        app(NotificationProcessor::class)->process($failure);

        Notification::assertSentToTimes($this->user, PaymentFailedNotification::class, 1);
        Notification::assertSentTo($this->user, PaymentFailedNotification::class, fn (PaymentFailedNotification $n): bool => $n->graceEndsAt->equalTo($graceEndsAt)
            && $n->provider === PaymentProvider::AppStore);

        $this->process(Type::GracePeriodExpired);

        Notification::assertSentToTimes($this->user, PaymentFailedNotification::class, 2);
        Notification::assertSentTo($this->user, PaymentFailedNotification::class, fn (PaymentFailedNotification $n): bool => $n->graceEndsAt === null);
    }

    public function test_a_guest_is_never_emailed_about_a_failed_renewal(): void
    {
        Notification::fake();
        $this->user = User::factory()->guest()->create();
        $this->subscribe(['expiresDate' => now()->subHour()->getTimestampMs()]);

        $this->process(Type::DidFailToRenew);

        $this->assertSame(SubscriptionStatus::Failed, $this->user->subscriptions()->sole()->status);
        Notification::assertNothingSent();
    }

    public function test_the_payment_failed_mail_varies_by_grace_and_links_to_the_store(): void
    {
        $inGrace = new PaymentFailedMail('Pro', PaymentProvider::AppStore, now()->addDays(3));
        $inGrace->assertHasSubject('Action Needed: Payment for Pro Failed');
        $inGrace->assertSeeInHtml('https://apps.apple.com/account/subscriptions');

        $paused = new PaymentFailedMail('Pro', PaymentProvider::Local);
        $paused->assertHasSubject('Your Pro Subscription Is Paused');
        $paused->assertDontSeeInHtml('Update Payment Method');
    }
}
