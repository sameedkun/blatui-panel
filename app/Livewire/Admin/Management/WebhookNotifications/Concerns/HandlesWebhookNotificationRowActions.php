<?php

namespace App\Livewire\Admin\Management\WebhookNotifications\Concerns;

use App\Contracts\RedispatchableNotification;
use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\PaymentProvider;
use App\Livewire\Admin\Management\BlockedIps\Concerns\HandlesIpActivityPanel;
use App\Support\WebhookNotificationRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * "Reprocess" action shared by the Index and Show pages — same pattern as
 * {@see HandlesIpActivityPanel}
 * and friends. Requires the using component to also use LogsAdminActivity and
 * HasToast. Only re-fires the provider's own webhook-received event with the
 * row's already-stored data ({@see RedispatchableNotification::redispatch()})
 * — there's no processing pipeline listening yet, so this doesn't (and
 * shouldn't) touch `processed`/`processed_at` itself; that stays owned by
 * whatever listener is wired up later.
 */
trait HandlesWebhookNotificationRowActions
{
    public ?string $redispatchProvider = null;

    public ?int $redispatchId = null;

    public function confirmRedispatch(string $provider, int $id): void
    {
        $this->authorize('webhook_notifications.manage');

        $this->redispatchProvider = $provider;
        $this->redispatchId = $id;
        $this->dispatch('open-alert-dialog-redispatch-webhook-notification');
    }

    public function redispatch(): void
    {
        $this->authorize('webhook_notifications.manage');

        $provider = PaymentProvider::tryFrom((string) $this->redispatchProvider);
        $notification = $provider ? WebhookNotificationRegistry::resolve($provider, $this->redispatchId) : null;

        abort_unless($notification instanceof RedispatchableNotification && $notification instanceof Model, 404);

        $notification->redispatch();

        $this->logActivity(ActivityModule::WebhookNotification, ActivityAction::Updated, $notification, [
            'type' => 'notification_redispatched',
            'provider' => $provider->value,
            'notification_type' => $notification->notificationType(),
        ]);

        $this->redispatchProvider = null;
        $this->redispatchId = null;
        $this->toastSuccess(__('webhook_notifications.toasts.redispatched'));
    }
}
