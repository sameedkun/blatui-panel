<?php

namespace App\Livewire\Admin\Management\Users;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Jobs\Account\BulkForceDeleteAccounts;
use App\Jobs\Account\BulkPurgeAccounts;
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
                    'label' => __('users.actions.restore'),
                    'icon' => 'rotate-ccw',
                    'confirm' => true,
                    'permission' => 'users.restore',
                ],
                [
                    'key' => 'force-delete',
                    'label' => __('users.actions.force_delete'),
                    'icon' => 'trash-2',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'permission' => 'users.force-delete',
                ],
            ],
            'pending' => [
                [
                    'key' => 'stop-deletion',
                    'label' => __('users.actions.stop_deletion'),
                    'icon' => 'shield-check',
                    'confirm' => true,
                    'permission' => 'users.delete',
                ],
                [
                    'key' => 'instant-purge',
                    'label' => __('users.actions.purge'),
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
                    'label' => __('users.actions.ban'),
                    'icon' => 'ban',
                    'confirm' => true,
                    'dialog_event' => 'open-dialog-bulk-ban',
                    'permission' => 'users.ban',
                ],
                [
                    'key' => 'unban',
                    'label' => __('users.actions.unban'),
                    'icon' => 'shield-check',
                    'confirm' => true,
                    'permission' => 'users.unban',
                ],
                [
                    'key' => 'schedule-deletion',
                    'label' => __('users.actions.schedule_deletion'),
                    'icon' => 'clock',
                    'confirm' => true,
                    'dialog_event' => 'open-dialog-bulk-schedule-deletion',
                    'permission' => 'users.delete',
                ],
                [
                    'key' => 'delete',
                    'label' => __('users.actions.delete'),
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
        $reason = trim($this->bulkBanReason) ?: __('users.defaults.ban_reason');
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
        $this->toastSuccess(__('users.toasts.bulk_banned', ['count' => $count]));
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
        $this->toastSuccess(__('users.toasts.bulk_unbanned', ['count' => $count]));
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
        $this->toastSuccess(__('users.toasts.bulk_deleted', ['count' => $count]));
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
        $this->toastSuccess(__('users.toasts.bulk_restored', ['count' => $count]));
    }

    /**
     * Runs each row through {@see DeletionService::forceDeleteRecord()} rather
     * than a single bulk `whereIn(...)->forceDelete()` — that raw query bypasses
     * related-data cleanup entirely (orphaning tokens/sessions/avatar files, and
     * risking an FK violation on `blocked_ips`, whose `user_id` is
     * restrictOnDelete()) and would blow up the whole batch on the first such
     * row. Still one bulk audit entry, matching every other bulk action here.
     *
     * A selection past {@see bulkQueueThreshold()} is handed to
     * {@see BulkForceDeleteAccounts} instead — each row's own transaction plus
     * a Storage::delete() call for its avatar makes a large synchronous batch
     * risk the request timeout. A selection past {@see bulkMaxSelection()} is
     * rejected outright, queued or not.
     */
    public function executeBulkForceDelete(DeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $ids = $this->selectedIds;
        $count = count($ids);

        if (! $this->withinBulkSelectionLimit($count)) {
            return;
        }

        if ($count > $this->bulkQueueThreshold()) {
            BulkForceDeleteAccounts::dispatch($ids, ActivityModule::User, auth()->id());

            $this->clearSelection();
            $this->toastSuccess(__('common.bulk_action_queued', ['count' => $count]));

            return;
        }

        $users = User::withTrashed()->whereIn('id', $ids)->get();

        $this->logActivity(ActivityModule::User, ActivityAction::ForceDeleted, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $users->count(),
        ]);

        $users->each(fn (User $user) => $deletions->forceDeleteRecord($user));

        $this->clearSelection();
        $this->toastSuccess(__('users.toasts.bulk_permanently_deleted', ['count' => $users->count()]));
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
        $this->toastSuccess(__('users.toasts.bulk_scheduled_deletion', ['count' => $count]));
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
        $this->toastSuccess(__('users.toasts.bulk_deletion_cancelled', ['count' => $count]));
    }

    /**
     * A selection past {@see bulkQueueThreshold()} is handed to
     * {@see BulkPurgeAccounts} instead of looping inline — same reasoning as
     * {@see executeBulkForceDelete()}.
     */
    public function executeBulkInstantPurge(DeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $ids = $this->selectedIds;
        $reason = trim($this->bulkPurgeReason) ?: null;

        if (! $this->withinBulkSelectionLimit(count($ids))) {
            return;
        }

        if (count($ids) > $this->bulkQueueThreshold()) {
            BulkPurgeAccounts::dispatch($ids, 'app', $reason, auth()->id());

            $this->clearSelection();
            $this->bulkPurgeReason = '';
            $this->toastSuccess(__('common.bulk_action_queued', ['count' => count($ids)]));

            return;
        }

        $count = 0;

        User::query()->appUsers()->whereIn('id', $ids)->get()
            ->each(function (User $user) use ($deletions, $reason, &$count): void {
                $deletions->instantPurgeByAdmin($user, $reason);
                $count++;
            });

        $this->clearSelection();
        $this->bulkPurgeReason = '';
        $this->toastSuccess(__('users.toasts.bulk_permanently_deleted', ['count' => $count]));
    }

    /** Above this many selected accounts, a destructive bulk action is queued instead of run inline. */
    private function bulkQueueThreshold(): int
    {
        return (int) config('panel.bulk_account_action_queue_threshold', 100);
    }

    /** Hard ceiling — past this, a destructive bulk action is rejected outright, queued or not. */
    private function bulkMaxSelection(): int
    {
        return (int) config('panel.bulk_account_action_max_selection', 1000);
    }

    /**
     * Rejects an oversized selection before any work starts. The selection
     * itself is left intact (no clearSelection()) so the admin can trim it
     * and retry rather than losing their picks.
     */
    private function withinBulkSelectionLimit(int $count): bool
    {
        $max = $this->bulkMaxSelection();

        if ($count <= $max) {
            return true;
        }

        $this->toastError(__('common.bulk_selection_too_large', ['count' => $count, 'max' => $max]));

        return false;
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
