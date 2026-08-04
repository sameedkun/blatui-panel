<?php

namespace App\Livewire\Admin\Management\Users;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Users\Concerns\HandlesUserRowActions;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use HandlesUserRowActions;
    use LogsAdminActivity;

    /** Primary status view: 'active' | 'pending' | 'trashed'. */
    public string $tab = 'active';

    // ── Bulk reason state (single-row state lives in HandlesUserRowActions) ────

    public string $bulkDeletionReason = '';

    public string $bulkPurgeReason = '';

    // ── Filters ───────────────────────────────────────────────────────────────

    public array $filters = [
        'status' => [],
        'email_verified' => '',
        'social' => [],
        'registered_from' => '',
        'registered_to' => '',
    ];

    /** Switch the primary status view, clearing any cross-tab selection. */
    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['active', 'pending', 'trashed'], true) ? $tab : 'active';
        $this->clearSelection();
        $this->resetPage();
    }

    // ── Base query ────────────────────────────────────────────────────────────
    protected function baseQuery(): Builder
    {
        $query = User::query()->appUsers()->with('activeSubscription.plan');

        // Pending-deletion accounts live only in the 'pending' tab, never 'active'.
        return match ($this->tab) {
            'trashed' => $query->onlyTrashed(),
            'pending' => $query->pendingDeletion(),
            default => $query->whereNull('deletion_requested_at'),
        };
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'external_id'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('users.filters.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('users.filters.active'), 'banned' => __('users.filters.banned')],
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
            'email_verified' => [
                'label' => __('users.filters.email_verified'),
                'type' => 'select',
                'options' => ['verified' => __('users.filters.verified'), 'unverified' => __('users.filters.unverified')],
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'verified' => $q->whereNotNull('email_verified_at'),
                    'unverified' => $q->whereNull('email_verified_at'),
                    default => $q,
                },
            ],
            'social' => [
                'label' => __('users.filters.social'),
                'type' => 'multi-select',
                'options' => ['google' => __('users.filters.google'), 'apple' => __('users.filters.apple')],
                'apply' => function (Builder $q, array $values): Builder {
                    return $q->where(function (Builder $sub) use ($values): void {
                        foreach ($values as $v) {
                            match ($v) {
                                'google' => $sub->orWhereNotNull('google_id'),
                                'apple' => $sub->orWhereNotNull('apple_id'),
                                default => null,
                            };
                        }
                    });
                },
            ],
            'registered_from' => [
                'label' => __('users.filters.registered_from'),
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '>=', $v),
            ],
            'registered_to' => [
                'label' => __('users.filters.registered_to'),
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
                'label' => __('users.stats.total_users'),
                'value' => fn () => User::appUsers()->count(),
                'icon' => 'users',
                'description' => __('users.stats.all_registered_accounts'),
            ],
            [
                'label' => __('users.stats.active'),
                'value' => fn () => User::appUsers()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => __('users.stats.not_banned'),
            ],
            [
                'label' => __('users.stats.banned'),
                'value' => fn () => User::appUsers()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => __('users.stats.banned_accounts'),
            ],
            [
                'label' => __('users.stats.new_this_month'),
                'value' => fn () => User::appUsers()->whereMonth('registration_date', now()->month)
                    ->whereYear('registration_date', now()->year)
                    ->count(),
                'icon' => 'user-plus',
                'description' => __('users.stats.joined_this_month'),
            ],
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => __('users.filters.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('users.filters.active'), 'banned' => __('users.filters.banned')],
            ],
            'email_verified' => [
                'label' => __('users.filters.email'),
                'type' => 'select',
                'options' => ['verified' => __('users.filters.verified'), 'unverified' => __('users.filters.unverified')],
            ],
            'social' => [
                'label' => __('users.filters.social'),
                'type' => 'multi-select',
                'options' => ['google' => __('users.filters.google'), 'apple' => __('users.filters.apple')],
            ],
            'registered' => [
                'label' => __('users.filters.registered'),
                'type' => 'date-range',
                'from_key' => 'registered_from',
                'to_key' => 'registered_to',
            ],
        ];
    }

    // ── Bulk action config (varies by status tab) ─────────────────────────────

    protected function bulkActionConfig(): array
    {
        return match ($this->tab) {
            'trashed' => [
                [
                    'key' => 'restore',
                    'label' => 'Restore',
                    'icon' => 'rotate-ccw',
                    'confirm' => true,
                    'permission' => 'users.restore',
                ],
                [
                    'key' => 'force-delete',
                    'label' => 'Permanently Delete',
                    'icon' => 'trash-2',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'permission' => 'users.force-delete',
                ],
            ],
            'pending' => [
                [
                    'key' => 'stop-deletion',
                    'label' => 'Stop Deletion',
                    'icon' => 'shield-check',
                    'confirm' => true,
                    'permission' => 'users.delete',
                ],
                [
                    'key' => 'instant-purge',
                    'label' => 'Purge Now',
                    'icon' => 'trash-2',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'dialog_event' => 'open-dialog-bulk-instant-purge',
                    'permission' => 'users.force-delete',
                ],
            ],
            default => [
                [
                    'key' => 'ban',
                    'label' => 'Ban',
                    'icon' => 'ban',
                    'confirm' => true,
                    'dialog_event' => 'open-dialog-bulk-ban',
                    'permission' => 'users.ban',
                ],
                [
                    'key' => 'unban',
                    'label' => 'Unban',
                    'icon' => 'shield-check',
                    'confirm' => true,
                    'permission' => 'users.unban',
                ],
                [
                    'key' => 'schedule-deletion',
                    'label' => 'Schedule Deletion',
                    'icon' => 'clock',
                    'confirm' => true,
                    'dialog_event' => 'open-dialog-bulk-schedule-deletion',
                    'permission' => 'users.delete',
                ],
                [
                    'key' => 'delete',
                    'label' => 'Delete',
                    'icon' => 'trash',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'permission' => 'users.delete',
                ],
            ],
        };
    }

    // ── Bulk actions (single-row actions live in HandlesUserRowActions) ───────

    public function executeBulkBan(): void
    {
        $this->authorize('users.ban');

        $ids = $this->selectedIds;
        $reason = trim($this->bulkBanReason) ?: 'Banned by administrator.';
        $count = User::whereIn('id', $ids)->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
        ]);

        $this->logActivity(ActivityModule::User, ActivityAction::Banned, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
            'ban_reason' => $reason,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} users banned.");
    }

    public function executeBulkUnban(): void
    {
        $this->authorize('users.unban');

        $ids = $this->selectedIds;
        $count = User::whereIn('id', $ids)
            ->update(['banned_at' => null, 'ban_reason' => null]);

        $this->logActivity(ActivityModule::User, ActivityAction::Unbanned, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} users unbanned.");
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('users.delete');

        $ids = $this->selectedIds;
        $count = count($ids);
        User::whereIn('id', $ids)->delete();

        $this->logActivity(ActivityModule::User, ActivityAction::Deleted, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} users deleted.");
    }

    public function executeBulkRestore(): void
    {
        $this->authorize('users.restore');

        $ids = $this->selectedIds;
        $count = count($ids);
        User::withTrashed()->whereIn('id', $ids)->restore();

        $this->logActivity(ActivityModule::User, ActivityAction::Restored, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} users restored.");
    }

    public function executeBulkForceDelete(): void
    {
        $this->authorize('users.force-delete');

        $ids = $this->selectedIds;
        $count = count($ids);

        $this->logActivity(ActivityModule::User, ActivityAction::ForceDeleted, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $count,
        ]);

        User::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->clearSelection();
        $this->toastSuccess("{$count} users permanently deleted.");
    }

    public function executeBulkScheduleDeletion(DeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $reason = trim($this->bulkDeletionReason) ?: null;
        $count = 0;

        // Fetch first, then mutate — avoids chunking while rows change scope.
        User::query()->appUsers()->whereIn('id', $this->selectedIds)->get()
            ->each(function (User $user) use ($deletions, $reason, &$count): void {
                $deletions->requestByAdmin($user, $reason);
                $count++;
            });

        $this->clearSelection();
        $this->bulkDeletionReason = '';
        $this->toastSuccess("{$count} users scheduled for deletion.");
    }

    public function executeBulkStopDeletion(DeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $count = 0;

        User::query()->appUsers()->pendingDeletion()->whereIn('id', $this->selectedIds)->get()
            ->each(function (User $user) use ($deletions, &$count): void {
                $deletions->cancelByAdmin($user);
                $count++;
            });

        $this->clearSelection();
        $this->toastSuccess("Deletion cancelled for {$count} users.");
    }

    public function executeBulkInstantPurge(DeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $reason = trim($this->bulkPurgeReason) ?: null;
        $count = 0;

        User::query()->appUsers()->whereIn('id', $this->selectedIds)->get()
            ->each(function (User $user) use ($deletions, $reason, &$count): void {
                $deletions->instantPurgeByAdmin($user, $reason);
                $count++;
            });

        $this->clearSelection();
        $this->bulkPurgeReason = '';
        $this->toastSuccess("{$count} users permanently deleted.");
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $users = $this->getRecords();

        return view('livewire.admin.management.users.index', [
            'users' => $users,
            'pageIds' => $users->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('users.title'));
    }
}
