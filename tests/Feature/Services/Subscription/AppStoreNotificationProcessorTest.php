<?php

namespace Tests\Feature\Services\Subscription;

use App\Enum\AppleNotificationSubtype as Subtype;
use App\Enum\AppleNotificationType as Type;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Mail\Billing\PaymentFailedMail;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhooks\AppleNotification;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Services\Webhooks\AppStore\NotificationProcessor;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    /** @return list<ReceiptType> */
    private function receiptTypes(Subscription $subscription): array
    {
        return $subscription->receipts()->orderBy('id')->pluck('type')->all();
    }

    public function test_a_free_trial_starts_as_trialing_with_nothing_paid(): void
    {
        $subscription = $this->subscribe(['offerType' => 1, 'offerDiscountType' => 'FREE_TRIAL', 'price' => 0, 'expiresDate' => now()->addWeek()->getTimestampMs()]);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->equalTo($subscription->ends_at));
        $this->assertSame('0.00', $subscription->amount_paid);
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
        $this->assertSame([ReceiptType::Initial, ReceiptType::Renewal], $this->receiptTypes($subscription));
        $this->assertSame(1, $this->user->subscriptions()->count());
    }

    public function test_a_renewal_after_a_free_trial_converts_it_and_records_the_first_charge(): void
    {
        $subscription = $this->subscribe(['offerType' => 1, 'offerDiscountType' => 'FREE_TRIAL', 'price' => 0, 'expiresDate' => now()->addWeek()->getTimestampMs()]);

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'expiresDate' => now()->addWeek()->addMonth()->getTimestampMs()]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('9.99', $subscription->amount_paid);
    }

    public function test_a_renewal_onto_another_product_chains_a_new_subscription(): void
    {
        $original = $this->subscribe();

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);

        $current = $this->user->activeSubscription()->first();
        $this->assertSame($this->yearly->id, $current->plan_price_id);
        $this->assertSame($original->id, $current->previous_subscription_id);
        $this->assertSame('99.99', $current->amount_paid);
        $this->assertSame([ReceiptType::PlanChange], $this->receiptTypes($current));
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
        $this->assertSame([ReceiptType::Initial, ReceiptType::Cancellation, ReceiptType::Reactivation], $this->receiptTypes($subscription));
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
        $this->assertSame([ReceiptType::Initial, ReceiptType::Refund, ReceiptType::RefundReversed], $this->receiptTypes($subscription));
    }

    public function test_refunding_an_earlier_period_only_records_the_refund(): void
    {
        $subscription = $this->subscribe(['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs()]);
        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'expiresDate' => now()->addMonth()->getTimestampMs()]);

        $this->process(Type::Refund, null, ['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs(), 'revocationReason' => 0]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertContains(ReceiptType::Refund, $this->receiptTypes($subscription));
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

        $this->assertSame([ReceiptType::Initial, ReceiptType::Renewal], $this->receiptTypes($subscription));
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

    public function test_renewals_accumulate_into_amount_paid_and_refunds_net_out(): void
    {
        $subscription = $this->subscribe();
        $renewal = ['transactionId' => '2000000000000002', 'expiresDate' => now()->addMonths(2)->getTimestampMs()];

        $notification = $this->process(Type::DidRenew, null, $renewal);
        app(NotificationProcessor::class)->process($notification);
        $this->assertSame('19.98', $subscription->refresh()->amount_paid);

        $this->process(Type::Refund, null, [...$renewal, 'revocationDate' => now()->getTimestampMs()]);
        $this->assertSame('9.99', $subscription->refresh()->amount_paid);

        $this->process(Type::RefundReversed, null, $renewal);
        $this->assertSame('19.98', $subscription->refresh()->amount_paid);
    }

    public function test_a_charge_in_another_currency_is_not_summed_into_the_row(): void
    {
        $subscription = $this->subscribe();

        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'price' => 8990, 'currency' => 'EUR', 'expiresDate' => now()->addMonths(2)->getTimestampMs()]);

        $this->assertSame('9.99', $subscription->refresh()->amount_paid);
        $this->assertSame('EUR', $subscription->receipts()->where('type', ReceiptType::Renewal)->sole()->payload['currency']);
    }

    public function test_a_refund_nets_against_the_row_that_took_the_charge(): void
    {
        $original = $this->subscribe(['transactionId' => '2000000000000001']);
        $this->process(Type::DidRenew, null, ['transactionId' => '2000000000000002', 'productId' => self::YEARLY, 'price' => 99990, 'expiresDate' => now()->addYear()->getTimestampMs()]);
        $current = $this->user->activeSubscription()->first();

        $this->process(Type::Refund, null, ['transactionId' => '2000000000000001', 'expiresDate' => now()->addDay()->getTimestampMs()]);

        $this->assertSame('0.00', $original->refresh()->amount_paid);
        $this->assertSame('99.99', $current->refresh()->amount_paid);
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
