<?php

namespace App\Livewire\Admin\Management\WebhookNotifications;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\WebhookNotifications\Concerns\HandlesWebhookNotificationRowActions;
use App\Support\WebhookNotificationRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

/**
 * Detail page for a single raw provider webhook notification. Route params
 * are `{provider}/{id}` rather than route-model binding — the model class
 * varies per provider, so resolution goes through
 * {@see WebhookNotificationRegistry} instead.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesWebhookNotificationRowActions;
    use LogsAdminActivity;

    public string $provider = '';

    public function mount(string $provider, int $id): void
    {
        $paymentProvider = PaymentProvider::tryFrom($provider);
        $notification = $paymentProvider ? WebhookNotificationRegistry::resolve($paymentProvider, $id) : null;

        abort_unless($notification instanceof Model, 404);

        $this->provider = $provider;
        $this->initShow($notification);
    }

    protected function indexRoute(): string
    {
        return 'admin.webhook-notifications.index';
    }

    protected function title(): string
    {
        return $this->notification()->notificationType();
    }

    protected function viewPermission(): ?string
    {
        return 'webhook_notifications.view';
    }

    public function notification(): ProviderNotification
    {
        /** @var ProviderNotification $record */
        $record = $this->record;

        return $record;
    }

    public function render(): View
    {
        return view('livewire.admin.management.webhook-notifications.show', [
            'notification' => $this->notification(),
            'providerLabel' => PaymentProvider::from($this->provider)->label(),
        ])->title(__('webhook_notifications.title').' — '.$this->title());
    }
}
