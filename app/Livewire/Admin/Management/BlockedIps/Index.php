<?php

namespace App\Livewire\Admin\Management\BlockedIps;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\BlockedIps\Concerns\HandlesIpActivityPanel;
use App\Models\BlockedIp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * CRUD list for blocked IPs — no Show page (a blocked_ips row is five fields;
 * a Show page would display nothing this table doesn't already). Create/edit
 * are routed pages ({@see Form}, same pattern as every other module's Form);
 * "who's behind this IP" is the {@see HandlesIpActivityPanel} drawer in place
 * of a Show page.
 */
#[Layout('layouts.admin.app')]
#[Title('Blocked IPs')]
class Index extends BaseIndex
{
    use HandlesIpActivityPanel;
    use LogsAdminActivity;

    public string $sortBy = 'hits';

    public string $sortDir = 'desc';

    public array $filters = [
        'scope' => '',
        'expired' => '',
    ];

    /** Confirmation state for the "delete all expired" action (not row-selection driven). */
    public bool $confirmingDeleteAllExpired = false;

    public ?int $deletingId = null;

    protected function baseQuery(): Builder
    {
        return BlockedIp::query()->with(['user:id,name,email', 'blockedBy:id,name']);
    }

    protected function searchableColumns(): array
    {
        return ['ip_address'];
    }

    protected function filterConfig(): array
    {
        return [
            'scope' => [
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'global' => $q->whereNull('user_id'),
                    'user' => $q->whereNotNull('user_id'),
                    default => $q,
                },
            ],
            'expired' => [
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'yes' => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                    'no' => $q->active(),
                    default => $q,
                },
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'scope' => [
                'label' => 'Scope',
                'type' => 'select',
                'options' => ['global' => 'Global', 'user' => 'Per-User'],
            ],
            'expired' => [
                'label' => 'Expired',
                'type' => 'select',
                'options' => ['yes' => 'Expired', 'no' => 'Not Expired'],
            ],
        ];
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Blocks',
                'value' => fn () => BlockedIp::count(),
                'icon' => 'shield-alert',
                'description' => 'All rules',
            ],
            [
                'label' => 'Active',
                'value' => fn () => BlockedIp::active()->count(),
                'icon' => 'shield-check',
                'description' => 'Currently enforced',
            ],
            [
                'label' => 'Expired',
                'value' => fn () => BlockedIp::whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
                'icon' => 'shield-off',
                'description' => 'Prunable',
            ],
            [
                'label' => 'Global',
                'value' => fn () => BlockedIp::global()->count(),
                'icon' => 'globe',
                'description' => 'Block every user',
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'delete',
                'label' => 'Delete',
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'blocked-ips.delete',
            ],
        ];
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('blocked-ips.delete');

        $ids = $this->selectedIds;
        $blockedIps = BlockedIp::whereIn('id', $ids)->get();
        $count = $blockedIps->count();

        BlockedIp::whereIn('id', $ids)->delete();
        BlockedIp::forgetCache($blockedIps->pluck('ip_address'));

        $this->logActivity(ActivityModule::BlockedIp, ActivityAction::Deleted, null, [
            'bulk' => true,
            'blocked_ip_ids' => $ids,
            'ip_addresses' => $blockedIps->pluck('ip_address')->all(),
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} blocks deleted.");
    }

    public function confirmDeleteAllExpired(): void
    {
        $this->authorize('blocked-ips.delete');

        $this->confirmingDeleteAllExpired = true;
        $this->dispatch('open-drawer-delete-all-expired');
    }

    public function expiredCount(): int
    {
        return BlockedIp::whereNotNull('expires_at')->where('expires_at', '<=', now())->count();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('blocked-ips.delete');

        $this->deletingId = $id;
        $this->dispatch('open-drawer-delete-blocked-ip');
    }

    public function delete(): void
    {
        $this->authorize('blocked-ips.delete');

        $blockedIp = BlockedIp::findOrFail($this->deletingId);
        $ip = $blockedIp->ip_address;
        $blockedIp->delete();

        $this->logActivity(ActivityModule::BlockedIp, ActivityAction::Deleted, null, [
            'ip_address' => $ip,
        ]);

        $this->deletingId = null;
        $this->toastSuccess("Block on {$ip} removed.");
    }

    public function deleteAllExpired(): void
    {
        $this->authorize('blocked-ips.delete');

        $expired = BlockedIp::whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
        $count = $expired->count();

        if ($count > 0) {
            BlockedIp::whereIn('id', $expired->pluck('id'))->delete();
            BlockedIp::forgetCache($expired->pluck('ip_address'));

            $this->logActivity(ActivityModule::BlockedIp, ActivityAction::Deleted, null, [
                'bulk' => true,
                'type' => 'expired_manual_purge',
                'blocked_ip_ids' => $expired->pluck('id')->all(),
                'count' => $count,
            ]);
        }

        $this->confirmingDeleteAllExpired = false;
        $this->toastSuccess("{$count} expired blocks deleted.");
    }

    public function render(): View
    {
        $blockedIps = $this->getRecords();

        return view('livewire.admin.management.blocked-ips.index', [
            'blockedIps' => $blockedIps,
            'pageIds' => $blockedIps->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
