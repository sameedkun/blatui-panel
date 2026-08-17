<?php

namespace App\Livewire\Admin\Management\Guests;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Jobs\Account\BulkForceDeleteAccounts;
use App\Jobs\Account\BulkPurgeAccounts;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Guests\Concerns\HandlesGuestRowActions;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
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
                'label' => __('guests.filters.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('guests.filters.active'), 'banned' => __('guests.filters.banned')],
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
                'label' => __('guests.filters.registered_from'),
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '>=', $v),
            ],
            'registered_to' => [
                'label' => __('guests.filters.registered_to'),
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
                'label' => __('guests.stats.total_guests'),
                'value' => fn () => User::guests()->count(),
                'icon' => 'user',
                'description' => __('guests.stats.all_registered_accounts'),
            ],
            [
                'label' => __('guests.stats.active'),
                'value' => fn () => User::guests()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => __('guests.stats.not_banned'),
            ],
            [
                'label' => __('guests.stats.banned'),
                'value' => fn () => User::guests()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => __('guests.stats.banned_accounts'),
            ],
            [
                'label' => __('guests.stats.new_this_month'),
                'value' => fn () => User::guests()
                    ->whereMonth('registration_date', now()->month)
                    ->whereYear('registration_date', now()->year)
                    ->count(),
                'icon' => 'user-plus',
                'description' => __('guests.stats.joined_this_month'),
            ],
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => __('guests.filters.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('guests.filters.active'), 'banned' => __('guests.filters.banned')],
            ],
            'registered' => [
                'label' => __('guests.filters.registered'),
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
                'label' => __('guests.actions.ban'),
                'icon' => 'ban',
                'confirm' => true,
                'dialog_event' => 'open-dialog-bulk-ban',
                'permission' => 'guests.ban',
            ],
            [
                'key' => 'unban',
                'label' => __('guests.actions.unban'),
                'icon' => 'shield-check',
                'confirm' => true,
                'permission' => 'guests.unban',
            ],
            [
                'key' => 'delete',
                'label' => __('guests.actions.delete'),
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
        $reason = trim($this->bulkBanReason) ?: __('guests.defaults.ban_reason');
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
        $this->toastSuccess(__('guests.toasts.bulk_banned', ['count' => $count]));
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
        $this->toastSuccess(__('guests.toasts.bulk_unbanned', ['count' => $count]));
    }

    /**
     * Instant, on-the-spot delete — permanently purges every selected guest and
     * their related data, same as the single-row {@see HandlesGuestRowActions::delete()}.
     * Fetches first, then mutates, since purging changes each row's scope mid-loop.
     *
     * A selection past {@see bulkQueueThreshold()} is handed to
     * {@see BulkPurgeAccounts} instead — each row's own transaction plus a
     * Storage::delete() call for its avatar makes a large synchronous batch
     * risk the request timeout. A selection past {@see bulkMaxSelection()} is
     * rejected outright, queued or not.
     */
    public function executeBulkDelete(DeletionService $deletions): void
    {
        $this->authorize('guests.delete');

        $ids = $this->selectedIds;

        if (! $this->withinBulkSelectionLimit(count($ids))) {
            return;
        }

        if (count($ids) > $this->bulkQueueThreshold()) {
            BulkPurgeAccounts::dispatch($ids, 'guest', null, auth()->id());

            $this->clearSelection();
            $this->toastSuccess(__('common.bulk_action_queued', ['count' => count($ids)]));

            return;
        }

        $count = 0;

        User::query()->guests()->whereIn('id', $ids)->get()
            ->each(function (User $guest) use ($deletions, &$count): void {
                $deletions->purgeGuestByAdmin($guest);
                $count++;
            });

        $this->clearSelection();
        $this->toastSuccess(__('guests.toasts.bulk_deleted', ['count' => $count]));
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
        $this->toastSuccess(__('guests.toasts.bulk_restored', ['count' => $count]));
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
     * {@see BulkForceDeleteAccounts} instead of running inline. A selection
     * past {@see bulkMaxSelection()} is rejected outright, queued or not.
     */
    public function executeBulkForceDelete(DeletionService $deletions): void
    {
        $this->authorize('guests.force-delete');

        $ids = $this->selectedIds;
        $count = count($ids);

        if (! $this->withinBulkSelectionLimit($count)) {
            return;
        }

        if ($count > $this->bulkQueueThreshold()) {
            BulkForceDeleteAccounts::dispatch($ids, ActivityModule::Guest, auth()->id());

            $this->clearSelection();
            $this->toastSuccess(__('common.bulk_action_queued', ['count' => $count]));

            return;
        }

        $guests = User::query()->guests()->withTrashed()->whereIn('id', $ids)->get();

        $this->logActivity(ActivityModule::Guest, ActivityAction::ForceDeleted, null, [
            'bulk' => true,
            'user_ids' => $ids,
            'count' => $guests->count(),
        ]);

        $guests->each(fn (User $guest) => $deletions->forceDeleteRecord($guest));

        $this->clearSelection();
        $this->toastSuccess(__('guests.toasts.bulk_permanently_deleted', ['count' => $guests->count()]));
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

        return view('livewire.admin.management.guests.index', [
            'users' => $users,
            'pageIds' => $users->reject(fn (User $user): bool => $user->trashed())->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('guests.title'));
    }
}
