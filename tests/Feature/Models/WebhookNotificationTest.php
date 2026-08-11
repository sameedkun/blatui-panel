<?php

namespace Tests\Feature\Models;

use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Enum\PaymentProvider;
use App\Models\SubscriptionReceipt;
use App\Models\Webhooks\AppleNotification;
use App\Support\WebhookNotificationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class WebhookNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_apple_notification_contract_methods_read_from_the_raw_row(): void
    {
        $notification = AppleNotification::factory()->create([
            'notification_type' => AppleNotificationType::DidRenew->value,
            'subtype' => AppleNotificationSubtype::Voluntary->value,
            'transaction_id' => 'txn-1',
            'original_transaction_id' => 'orig-1',
            'product_id' => 'com.example.app.monthly',
            'payload' => ['data' => ['environment' => 'Sandbox']],
            'processed' => false,
        ]);

        $this->assertSame('DID_RENEW', $notification->notificationType());
        $this->assertSame('Renewed', $notification->notificationTypeLabel());
        $this->assertSame('Voluntary', $notification->subtypeLabel());
        $this->assertSame('txn-1', $notification->transactionId());
        $this->assertSame('orig-1', $notification->originalTransactionId());
        $this->assertSame('com.example.app.monthly', $notification->productId());
        $this->assertSame('Sandbox', $notification->environment());
        $this->assertFalse($notification->isProcessed());
        $this->assertNull($notification->processedAt());
        $this->assertSame(['data' => ['environment' => 'Sandbox']], $notification->rawPayload());
    }

    public function test_apple_notification_without_a_subtype_has_no_subtype_label(): void
    {
        $notification = AppleNotification::factory()->create(['subtype' => null]);

        $this->assertNull($notification->subtypeLabel());
    }

    public function test_apple_notification_processed_state_reflects_processed_at(): void
    {
        $notification = AppleNotification::factory()->processed()->create();

        $this->assertTrue($notification->isProcessed());
        $this->assertNotNull($notification->processedAt());
    }

    public function test_registry_resolves_a_notification_for_a_registered_provider(): void
    {
        $notification = AppleNotification::factory()->create();

        $resolved = WebhookNotificationRegistry::resolve(PaymentProvider::AppStore, $notification->id);

        $this->assertInstanceOf(AppleNotification::class, $resolved);
        $this->assertSame($notification->id, $resolved->id);
    }

    public function test_registry_returns_null_for_an_unregistered_provider(): void
    {
        $this->assertNull(WebhookNotificationRegistry::resolve(PaymentProvider::Stripe, 1));
    }

    public function test_registry_returns_null_for_a_missing_id(): void
    {
        $this->assertNull(WebhookNotificationRegistry::resolve(PaymentProvider::AppStore, 999999));
    }

    public function test_registry_returns_null_when_provider_or_id_is_absent(): void
    {
        $this->assertNull(WebhookNotificationRegistry::resolve(null, 1));
        $this->assertNull(WebhookNotificationRegistry::resolve(PaymentProvider::AppStore, null));
    }

    public function test_subscription_receipt_resolves_its_linked_notification(): void
    {
        $notification = AppleNotification::factory()->create();
        $receipt = SubscriptionReceipt::factory()->create([
            'notification_provider' => PaymentProvider::AppStore->value,
            'notification_id' => $notification->id,
        ]);

        $resolved = $receipt->notification();

        $this->assertInstanceOf(AppleNotification::class, $resolved);
        $this->assertSame($notification->id, $resolved->id);
    }

    public function test_subscription_receipt_notification_is_null_when_unlinked(): void
    {
        $receipt = SubscriptionReceipt::factory()->create([
            'notification_provider' => null,
            'notification_id' => null,
        ]);

        $this->assertNull($receipt->notification());
    }

    public function test_english_and_turkish_webhook_notification_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('webhook_notifications', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('webhook_notifications', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_english_and_turkish_apple_notification_enum_translations_have_matching_keys(): void
    {
        foreach (['apple_notification_type', 'apple_notification_subtype'] as $group) {
            $englishKeys = array_keys(Lang::get("enums.{$group}", [], 'en'));
            $turkishKeys = array_keys(Lang::get("enums.{$group}", [], 'tr'));

            sort($englishKeys);
            sort($turkishKeys);

            $this->assertSame($englishKeys, $turkishKeys, "Mismatched keys for enums.{$group}");
        }
    }
}
