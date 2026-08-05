<?php

namespace App\Livewire\Admin\Management\WebhookNotifications;

use App\Enum\PaymentProvider;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\WebhookNotifications\Concerns\HandlesWebhookNotificationRowActions;
use App\Support\WebhookNotificationRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

/**
 * Raw provider webhook notification log, one provider at a time. Rather than
 * a lossy UNION across tables with different shapes per provider, this stays
 * a single-builder {@see BaseIndex} page whose builder is resolved per the
 * selected provider via {@see WebhookNotificationRegistry} — every provider
 * table keeps its own native columns/indexes.
 *
 * Relies on a soft convention (not enforced by the contract) that every
 * provider table names its shared columns identically to Apple's
 * (`transaction_id`, `original_transaction_id`, `product_id`, `processed`)
 * so search/filter here stays generic without per-provider branching.
 */
#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use HandlesWebhookNotificationRowActions;
    use LogsAdminActivity;

    #[Url]
    public string $provider = '';

    public array $filters = [
        'processed' => '',
    ];

    public function mount(): void
    {
        if (! array_key_exists($this->provider, WebhookNotificationRegistry::providers())) {
            $this->provider = (string) array_key_first(WebhookNotificationRegistry::providers());
        }
    }

    public function updatedProvider(): void
    {
        $this->resetPage();
    }

    protected function currentModelClass(): ?string
    {
        $provider = PaymentProvider::tryFrom($this->provider);

        return $provider ? WebhookNotificationRegistry::modelFor($provider) : null;
    }

    protected function baseQuery(): Builder
    {
        $model = $this->currentModelClass()
            ?? throw new \RuntimeException('No webhook notification providers are registered.');

        return $model::query();
    }

    protected function searchableColumns(): array
    {
        return ['transaction_id', 'original_transaction_id', 'product_id'];
    }

    protected function filterConfig(): array
    {
        return [
            'processed' => [
                'apply' => fn (Builder $q, string $v): Builder => $q->where('processed', $v === 'yes'),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'processed' => [
                'label' => __('webhook_notifications.filters.processed'),
                'type' => 'select',
                'options' => [
                    'yes' => __('webhook_notifications.values.processed'),
                    'no' => __('webhook_notifications.values.unprocessed'),
                ],
            ],
        ];
    }

    protected function statsConfig(): array
    {
        $model = $this->currentModelClass();

        return [
            [
                'label' => __('webhook_notifications.stats.total'),
                'value' => fn () => $model ? $model::count() : 0,
                'icon' => 'webhook',
                'description' => __('webhook_notifications.stats.total_description'),
            ],
            [
                'label' => __('webhook_notifications.stats.unprocessed'),
                'value' => fn () => $model ? $model::where('processed', false)->count() : 0,
                'icon' => 'circle-alert',
                'description' => __('webhook_notifications.stats.unprocessed_description'),
            ],
            [
                'label' => __('webhook_notifications.stats.today'),
                'value' => fn () => $model ? $model::whereDate('created_at', today())->count() : 0,
                'icon' => 'calendar',
                'description' => __('webhook_notifications.stats.today_description'),
            ],
        ];
    }

    public function render(): View
    {
        $notifications = $this->getRecords();

        return view('livewire.admin.management.webhook-notifications.index', [
            'notifications' => $notifications,
            'providers' => WebhookNotificationRegistry::providers(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('webhook_notifications.title'));
    }
}
