<?php

namespace Tests\Feature\Webhooks;

use App\Enum\ActivityContext;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Enum\TransactionType;
use App\Events\Webhooks\AppStoreWebhookReceived;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\User;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SignsAppStoreNotifications;
use Tests\TestCase;

class AppStoreWebhookTest extends TestCase
{
    use RefreshDatabase, SignsAppStoreNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trustTestAppStoreRoot();
        config([
            'services.app_store.bundle_id' => 'com.example.app',
            'services.app_store.environments' => ['Production'],
            'services.app_store.verify_url_signature' => true,
        ]);
    }

    private function postNotification(string $body, ?string $url = null): TestResponse
    {
        return $this->call(
            'POST',
            $url ?? URL::signedRoute('webhooks.appstore', absolute: false),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
    }

    private function mappedPrice(string $productId = 'com.example.app.pro.monthly'): PlanPrice
    {
        $price = PlanPrice::factory()->create(['amount' => 9.99]);
        PlanPriceProvider::factory()->create([
            'plan_price_id' => $price->id,
            'provider' => PaymentProvider::AppStore,
            'external_id' => $productId,
        ]);

        return $price;
    }

    public function test_an_initial_purchase_is_stored_and_grants_the_mapped_plan(): void
    {
        $user = User::factory()->app()->create();
        $price = $this->mappedPrice();

        $this->postNotification($this->appStoreNotificationBody(
            'SUBSCRIBED',
            'INITIAL_BUY',
            $this->appStoreTransaction(['appAccountToken' => $user->appAccountToken()]),
            $this->appStoreRenewal(),
        ))->assertOk()->assertJson(['status' => 'received']);

        $notification = AppleNotification::sole();
        $this->assertTrue($notification->processed);
        $this->assertSame('2000000000000001', $notification->original_transaction_id);
        $this->assertSame('Production', $notification->environment());
        $this->assertNotNull($notification->payload['signedPayload']);

        $subscription = $user->activeSubscription()->sole();
        $this->assertSame($price->id, $subscription->plan_price_id);
        $this->assertSame(PaymentProvider::AppStore, $subscription->provider);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->is_recurring);

        $transaction = $subscription->transactions()->sole();
        $this->assertSame(TransactionType::Initial, $transaction->type);
        $this->assertSame([999, 'USD'], [$transaction->amount_minor, $transaction->currency]);
        $this->assertSame([PaymentProvider::AppStore, $notification->id], [$transaction->notification_provider, $transaction->notification_id]);
        $this->assertSame($notification->id, $transaction->notification()->id);

        $activity = Activity::query()->where('properties->type', 'subscription_assigned')->sole();
        $this->assertSame(ActivityContext::Webhook->value, $activity->properties['context']);
        $this->assertNull($activity->causer_id);
    }

    public function test_a_redelivered_notification_is_not_applied_twice(): void
    {
        $user = User::factory()->app()->create();
        $this->mappedPrice();
        $body = $this->appStoreNotificationBody(
            'SUBSCRIBED',
            'INITIAL_BUY',
            $this->appStoreTransaction(['appAccountToken' => $user->appAccountToken()]),
        );

        $this->postNotification($body)->assertOk();
        $this->postNotification($body)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, AppleNotification::count());
        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_an_unprocessed_redelivery_is_handed_off_again(): void
    {
        $body = $this->appStoreNotificationBody('SUBSCRIBED', 'INITIAL_BUY', $this->appStoreTransaction());
        Event::fake([AppStoreWebhookReceived::class]);

        $this->postNotification($body)->assertOk();
        $this->postNotification($body)->assertOk()->assertJson(['status' => 'received']);

        Event::assertDispatchedTimes(AppStoreWebhookReceived::class, 2);
        $this->assertSame(1, AppleNotification::count());
    }

    public function test_a_request_without_a_valid_url_signature_is_rejected(): void
    {
        $this->postNotification($this->appStoreNotificationBody('TEST'), route('webhooks.appstore', absolute: false))
            ->assertForbidden();

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_the_url_signature_check_can_be_turned_off(): void
    {
        config(['services.app_store.verify_url_signature' => false]);

        $this->postNotification($this->appStoreNotificationBody('TEST'), route('webhooks.appstore', absolute: false))
            ->assertOk();

        $this->assertTrue(AppleNotification::sole()->processed);
    }

    public function test_a_payload_signed_by_an_untrusted_root_is_rejected(): void
    {
        $body = $this->appStoreNotificationBody('TEST');
        $this->trustUnrelatedAppStoreRoot();

        $this->postNotification($body)->assertStatus(400);

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_a_malformed_body_is_rejected(): void
    {
        $this->postNotification('{"signedPayload":"not-a-jws"}')->assertStatus(400);
        $this->postNotification('')->assertStatus(400);

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_a_notification_for_another_app_is_rejected(): void
    {
        $this->postNotification($this->appStoreNotificationBody('TEST', overrides: ['data' => ['bundleId' => 'com.someone.else']]))
            ->assertStatus(400);

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_a_missing_root_certificate_fails_closed_with_a_server_error(): void
    {
        config(['services.app_store.root_certificate' => storage_path('missing/AppleRootCA-G3.cer')]);

        $this->postNotification($this->appStoreNotificationBody('TEST'))->assertServerError();

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_an_unknown_notification_type_is_acknowledged_without_being_stored(): void
    {
        $this->postNotification($this->appStoreNotificationBody('SOMETHING_NEW'))
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        $this->assertSame(0, AppleNotification::count());
    }

    public function test_a_sandbox_notification_is_stored_but_does_not_touch_subscriptions_in_production_mode(): void
    {
        $user = User::factory()->app()->create();
        $this->mappedPrice();

        $this->postNotification($this->appStoreNotificationBody(
            'SUBSCRIBED',
            'INITIAL_BUY',
            $this->appStoreTransaction(['appAccountToken' => $user->appAccountToken(), 'environment' => 'Sandbox']),
            overrides: ['data' => ['environment' => 'Sandbox']],
        ))->assertOk();

        $this->assertTrue(AppleNotification::sole()->processed);
        $this->assertSame(0, $user->subscriptions()->count());
    }
}
