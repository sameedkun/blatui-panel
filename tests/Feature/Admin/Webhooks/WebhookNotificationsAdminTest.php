<?php

namespace Tests\Feature;

use App\Enum\PaymentProvider;
use App\Events\Webhooks\AppStoreWebhookReceived;
use App\Livewire\Admin\Management\Subscriptions\Show as SubscriptionShow;
use App\Livewire\Admin\Management\WebhookNotifications\Index;
use App\Livewire\Admin\Management\WebhookNotifications\Show;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebhookNotificationsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWith(array $permissions): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_index_is_forbidden_without_the_view_permission(): void
    {
        $this->actingAsAdminWith(['panel.access-admin']);

        $this->get(route('admin.webhook-notifications.index'))->assertForbidden();
    }

    public function test_show_is_forbidden_without_the_view_permission(): void
    {
        $this->actingAsAdminWith(['panel.access-admin']);
        $notification = AppleNotification::factory()->create();

        $this->get(route('admin.webhook-notifications.show', [
            'provider' => PaymentProvider::AppStore->value,
            'id' => $notification->id,
        ]))->assertForbidden();
    }

    public function test_index_lists_notifications_for_the_selected_provider(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);
        $notification = AppleNotification::factory()->create(['transaction_id' => 'txn-visible']);

        Livewire::test(Index::class)
            ->assertSet('provider', PaymentProvider::AppStore->value)
            ->assertSee('txn-visible');
    }

    public function test_index_filters_by_processed_state(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);
        AppleNotification::factory()->create(['transaction_id' => 'txn-unprocessed', 'processed' => false]);
        AppleNotification::factory()->processed()->create(['transaction_id' => 'txn-processed']);

        Livewire::test(Index::class)
            ->set('filters.processed', 'yes')
            ->assertSee('txn-processed')
            ->assertDontSee('txn-unprocessed');
    }

    public function test_show_resolves_the_correct_notification(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);
        $notification = AppleNotification::factory()->create(['transaction_id' => 'txn-detail']);

        Livewire::test(Show::class, ['provider' => PaymentProvider::AppStore->value, 'id' => $notification->id])
            ->assertSee('txn-detail');
    }

    public function test_show_404s_for_an_unregistered_provider(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);

        $this->get(route('admin.webhook-notifications.show', [
            'provider' => PaymentProvider::Stripe->value,
            'id' => 1,
        ]))->assertNotFound();
    }

    public function test_show_404s_for_a_nonexistent_id(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);

        $this->get(route('admin.webhook-notifications.show', [
            'provider' => PaymentProvider::AppStore->value,
            'id' => 999999,
        ]))->assertNotFound();
    }

    public function test_subscription_show_tab_renders_its_linked_notification(): void
    {
        $this->actingAsSuperAdmin();

        $notification = AppleNotification::factory()->create(['transaction_id' => 'txn-linked']);
        $subscription = Subscription::factory()->create();
        SubscriptionReceipt::factory()->for($subscription)->create([
            'notification_provider' => PaymentProvider::AppStore->value,
            'notification_id' => $notification->id,
        ]);

        Livewire::test(SubscriptionShow::class, ['subscription' => $subscription])
            ->set('tab', 'webhook_notifications')
            ->assertSee('txn-linked');
    }

    public function test_subscription_show_tab_is_unavailable_without_webhook_notifications_permission(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'subscriptions.manage']);

        $notification = AppleNotification::factory()->create(['transaction_id' => 'txn-hidden']);
        $subscription = Subscription::factory()->create();
        SubscriptionReceipt::factory()->for($subscription)->create([
            'notification_provider' => PaymentProvider::AppStore->value,
            'notification_id' => $notification->id,
        ]);

        Livewire::test(SubscriptionShow::class, ['subscription' => $subscription])
            ->set('tab', 'webhook_notifications')
            ->assertDontSee('txn-hidden');
    }

    public function test_redispatch_fires_the_apple_webhook_event_from_the_show_page(): void
    {
        Event::fake([AppStoreWebhookReceived::class]);
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view', 'webhook_notifications.manage']);
        $notification = AppleNotification::factory()->create();

        Livewire::test(Show::class, ['provider' => PaymentProvider::AppStore->value, 'id' => $notification->id])
            ->call('confirmRedispatch', PaymentProvider::AppStore->value, $notification->id)
            ->call('redispatch')
            ->assertDispatched('toast');

        Event::assertDispatched(
            AppStoreWebhookReceived::class,
            fn (AppStoreWebhookReceived $event): bool => $event->appleNotification->is($notification),
        );
    }

    public function test_redispatch_fires_the_apple_webhook_event_from_the_index_page(): void
    {
        Event::fake([AppStoreWebhookReceived::class]);
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view', 'webhook_notifications.manage']);
        $notification = AppleNotification::factory()->create();

        Livewire::test(Index::class)
            ->call('confirmRedispatch', PaymentProvider::AppStore->value, $notification->id)
            ->call('redispatch');

        Event::assertDispatched(AppStoreWebhookReceived::class);
    }

    public function test_redispatch_logs_activity(): void
    {
        Event::fake([AppStoreWebhookReceived::class]);
        $admin = $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view', 'webhook_notifications.manage']);
        $notification = AppleNotification::factory()->create();

        Livewire::test(Show::class, ['provider' => PaymentProvider::AppStore->value, 'id' => $notification->id])
            ->call('confirmRedispatch', PaymentProvider::AppStore->value, $notification->id)
            ->call('redispatch');

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $admin->id,
            'event' => 'updated',
            'subject_type' => AppleNotification::class,
            'subject_id' => $notification->id,
        ]);
    }

    public function test_redispatch_is_forbidden_without_the_manage_permission(): void
    {
        // Ensures the permission row exists (so Spatie denies via AuthorizationException)
        // without granting it to this admin's role — a permission that has never been
        // created at all throws Spatie's PermissionDoesNotExist instead.
        Permission::firstOrCreate(['name' => 'webhook_notifications.manage', 'guard_name' => 'web']);
        $this->actingAsAdminWith(['panel.access-admin', 'webhook_notifications.view']);
        $notification = AppleNotification::factory()->create();

        Livewire::test(Show::class, ['provider' => PaymentProvider::AppStore->value, 'id' => $notification->id])
            ->call('confirmRedispatch', PaymentProvider::AppStore->value, $notification->id)
            ->assertStatus(403);
    }
}
