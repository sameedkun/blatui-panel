<?php

namespace App\Livewire\Admin\Management\Guests;

use App\Livewire\Admin\BaseIndex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Guests')]
class Index extends BaseIndex
{
    // ── Single-row confirmation state ─────────────────────────────────────────

    public ?int $banningUserId = null;

    public string $banReason = '';

    public ?int $deletingId = null;

    public ?int $restoringId = null;

    public ?int $forceDeleteId = null;

    // ── Filters ───────────────────────────────────────────────────────────────

    public array $filters = [
        'status' => [],
        'registered_from' => '',
        'registered_to' => '',
        'view' => '',
    ];

    // ── Base query ────────────────────────────────────────────────────────────
    protected function baseQuery(): Builder
    {
        $query = User::query()->guests();

        if (($this->filters['view'] ?? '') === 'trashed') {
            $query->onlyTrashed();
        }

        return $query;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'external_id'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'multi-select',
                'options' => ['active' => 'Active', 'banned' => 'Banned'],
                'apply' => function (Builder $q, array $values): Builder {
                    return $q->where(function (Builder $sub) use ($values): void {
                        foreach ($values as $v) {
                            match ($v) {
                                'active' => $sub->orWhereNull('banned_at'),
                                'banned' => $sub->orWhereNotNull('banned_at'),
                                default => null,
                            };
                        }
                    });
                },
            ],
            'registered_from' => [
                'label' => 'Registered from',
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '>=', $v),
            ],
            'registered_to' => [
                'label' => 'Registered to',
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '<=', $v.' 23:59:59'),
            ],
            'view' => [
                'label' => 'View',
                'type' => 'select',
                'options' => ['trashed' => 'Deleted'],
                'apply' => fn (Builder $q, string $v): Builder => $q, // handled in baseQuery()
            ],
        ];
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Guests',
                'value' => fn () => User::guests()->count(),
                'icon' => 'user',
                'description' => 'All registered guest accounts',
            ],
            [
                'label' => 'Active',
                'value' => fn () => User::guests()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => 'Not banned',
            ],
            [
                'label' => 'Banned',
                'value' => fn () => User::guests()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => 'Banned accounts',
            ],
            [
                'label' => 'New This Month',
                'value' => fn () => User::guests()
                    ->whereMonth('registration_date', now()->month)
                    ->whereYear('registration_date', now()->year)
                    ->count(),
                'icon' => 'user-plus',
                'description' => 'Joined this month',
            ],
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'multi-select',
                'options' => ['active' => 'Active', 'banned' => 'Banned'],
            ],
            'registered' => [
                'label' => 'Registered',
                'type' => 'date-range',
                'from_key' => 'registered_from',
                'to_key' => 'registered_to',
            ],
            'view' => [
                'label' => 'Deleted',
                'type' => 'toggle',
                'active_value' => 'trashed',
                'active_label' => 'Showing Deleted',
                'icon' => 'trash-2',
            ],
        ];
    }

    // ── Bulk action config ────────────────────────────────────────────────────

    protected function bulkActionConfig(): array
    {
        $isTrashed = ($this->filters['view'] ?? '') === 'trashed';

        if ($isTrashed) {
            return [
                [
                    'key' => 'restore',
                    'label' => 'Restore',
                    'icon' => 'rotate-ccw',
                    'confirm' => true,
                    'permission' => 'guests.restore',
                ],
                [
                    'key' => 'force-delete',
                    'label' => 'Permanently Delete',
                    'icon' => 'trash-2',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'permission' => 'guests.force-delete',
                ],
            ];
        }

        return [
            [
                'key' => 'ban',
                'label' => 'Ban',
                'icon' => 'ban',
                'confirm' => true,
                'dialog_event' => 'open-dialog-bulk-ban',
                'permission' => 'guests.ban',
            ],
            [
                'key' => 'unban',
                'label' => 'Unban',
                'icon' => 'shield-check',
                'confirm' => true,
                'permission' => 'guests.unban',
            ],
            [
                'key' => 'delete',
                'label' => 'Delete',
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'guests.delete',
            ],
        ];
    }

    // ── Single-row actions ────────────────────────────────────────────────────

    public function openBanDialog(int $userId): void
    {
        $this->authorize('guests.ban');
        $this->banningUserId = $userId;
        $this->banReason = '';
        $this->dispatch('open-dialog-ban-user');
    }

    public function confirmBan(): void
    {
        $this->authorize('guests.ban');

        $user = User::findOrFail($this->banningUserId);
        $user->update([
            'banned_at' => now(),
            'ban_reason' => trim($this->banReason) ?: 'Banned by administrator.',
        ]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess("Guest {$user->name} has been banned.");
    }

    public function unban(int $userId): void
    {
        $this->authorize('guests.unban');

        $user = User::findOrFail($userId);
        $user->update(['banned_at' => null, 'ban_reason' => null]);

        $this->toastSuccess("{$user->name} has been unbanned.");
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('guests.delete');
        $this->deletingId = $userId;
        $this->dispatch('open-alert-dialog-delete-user');
    }

    public function delete(): void
    {
        $this->authorize('guests.delete');

        $user = User::findOrFail($this->deletingId);
        $name = $user->name;
        $user->delete();

        $this->deletingId = null;
        $this->toastSuccess("{$name} has been deleted.");
    }

    public function confirmRestore(int $userId): void
    {
        $this->authorize('guests.restore');
        $this->restoringId = $userId;
        $this->dispatch('open-alert-dialog-restore-user');
    }

    public function restore(): void
    {
        $this->authorize('guests.restore');

        $user = User::withTrashed()->findOrFail($this->restoringId);
        $user->restore();

        $this->restoringId = null;
        $this->toastSuccess("{$user->name} has been restored.");
    }

    public function confirmForceDelete(int $userId): void
    {
        $this->authorize('guests.force-delete');
        $this->forceDeleteId = $userId;
        $this->dispatch('open-alert-dialog-force-delete-user');
    }

    public function forceDelete(): void
    {
        $this->authorize('guests.force-delete');

        $user = User::withTrashed()->findOrFail($this->forceDeleteId);
        $name = $user->name;
        $user->forceDelete();

        $this->forceDeleteId = null;
        $this->toastSuccess("{$name} has been permanently deleted.");
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function executeBulkBan(): void
    {
        $this->authorize('guests.ban');

        $count = User::whereIn('id', $this->selectedIds)->update([
            'banned_at' => now(),
            'ban_reason' => trim($this->bulkBanReason) ?: 'Banned by administrator.',
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} guests banned.");
    }

    public function executeBulkUnban(): void
    {
        $this->authorize('guests.unban');

        $count = User::whereIn('id', $this->selectedIds)
            ->update(['banned_at' => null, 'ban_reason' => null]);

        $this->clearSelection();
        $this->toastSuccess("{$count} guests unbanned.");
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('guests.delete');

        $count = count($this->selectedIds);
        User::whereIn('id', $this->selectedIds)->delete();

        $this->clearSelection();
        $this->toastSuccess("{$count} guests deleted.");
    }

    public function executeBulkRestore(): void
    {
        $this->authorize('guests.restore');

        $count = count($this->selectedIds);
        User::withTrashed()->whereIn('id', $this->selectedIds)->restore();

        $this->clearSelection();
        $this->toastSuccess("{$count} guests restored.");
    }

    public function executeBulkForceDelete(): void
    {
        $this->authorize('guests.force-delete');

        $count = count($this->selectedIds);
        User::withTrashed()->whereIn('id', $this->selectedIds)->forceDelete();

        $this->clearSelection();
        $this->toastSuccess("{$count} guests permanently deleted.");
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $users = $this->getRecords();

        return view('livewire.admin.management.guests.index', [
            'users' => $users,
            'pageIds' => $users->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}