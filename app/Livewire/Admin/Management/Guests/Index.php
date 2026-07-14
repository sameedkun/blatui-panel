<?php

namespace App\Livewire\Admin\Management\Guests;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Guests\Concerns\HandlesGuestRowActions;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Guests')]
class Index extends BaseIndex
{
    use HandlesGuestRowActions;
    use LogsAdminActivity;

    // ── Filters ───────────────────────────────────────────────────────────────

    public array $filters = [
        'status' => [],
        'registered_from' => '',
        'registered_to' => '',
    ];

    // ── Base query ────────────────────────────────────────────────────────────
    protected function baseQuery(): Builder
    {
        // Deleted guests are rare (Delete is now instant/permanent), so instead
        // of a separate "trashed" filter, they're just shown inline with a badge.
        return User::query()->guests()->withTrashed();
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
        ];
    }

    // ── Bulk action config ────────────────────────────────────────────────────
    // Deleted (trashed) rows aren't selectable — see the checkbox column in the
    // view — so only the active-guest bulk actions apply here. Restore /
    // Force-Delete stay as row-only actions on trashed rows.

    protected function bulkActionConfig(): array
    {
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
    // Ban, delete, restore, force-delete come from HandlesGuestRowActions —
    // shared with the guest profile page so the two never drift apart.

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function executeBulkBan(): void
    {
        $this->authorize('guests.ban');

        $ids = $this->selectedIds;
        $reason = trim($this->bulkBanReason) ?: 'Banned by administrator.';
        $count = User::query()->guests()->whereIn('id', $ids)->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
        ]);

        $this->logActivity(ActivityModule::Guest, ActivityAction::Banned, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
            'ban_reason' => $reason,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} guests banned.");
    }

    public function executeBulkUnban(): void
    {
        $this->authorize('guests.unban');

        $ids = $this->selectedIds;
        $count = User::query()->guests()->whereIn('id', $ids)
            ->update(['banned_at' => null, 'ban_reason' => null]);

        $this->logActivity(ActivityModule::Guest, ActivityAction::Unbanned, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} guests unbanned.");
    }

    /**
     * Instant, on-the-spot delete — permanently purges every selected guest and
     * their related data, same as the single-row {@see HandlesGuestRowActions::delete()}.
     * Fetches first, then mutates, since purging changes each row's scope mid-loop.
     */
    public function executeBulkDelete(AccountDeletionService $deletions): void
    {
        $this->authorize('guests.delete');

        $count = 0;

        User::query()->guests()->whereIn('id', $this->selectedIds)->get()
            ->each(function (User $guest) use ($deletions, &$count): void {
                $deletions->purgeGuestByAdmin($guest);
                $count++;
            });

        $this->clearSelection();
        $this->toastSuccess("{$count} guests permanently deleted.");
    }

    public function executeBulkRestore(): void
    {
        $this->authorize('guests.restore');

        $ids = $this->selectedIds;
        $count = count($ids);
        User::query()->guests()->withTrashed()->whereIn('id', $ids)->restore();

        $this->logActivity(ActivityModule::Guest, ActivityAction::Restored, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} guests restored.");
    }

    public function executeBulkForceDelete(): void
    {
        $this->authorize('guests.force-delete');

        $ids = $this->selectedIds;
        $count = count($ids);

        $this->logActivity(ActivityModule::Guest, ActivityAction::ForceDeleted, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        User::query()->guests()->withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->clearSelection();
        $this->toastSuccess("{$count} guests permanently deleted.");
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $users = $this->getRecords();

        return view('livewire.admin.management.guests.index', [
            'users' => $users,
            'pageIds' => $users->reject(fn (User $user): bool => $user->trashed())->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
