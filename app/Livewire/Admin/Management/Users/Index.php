<?php

namespace App\Livewire\Admin\Management\Users;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Users')]
class Index extends BaseIndex
{
    use LogsAdminActivity;

    /** Primary status view: 'active' | 'pending' | 'trashed'. */
    public string $tab = 'active';

    // ── Single-row confirmation state ─────────────────────────────────────────

    public ?int $banningUserId = null;

    public string $banReason = '';

    public ?int $deletingId = null;

    public ?int $restoringId = null;

    public ?int $forceDeleteId = null;

    public ?int $schedulingId = null;

    public string $deletionReason = '';

    public ?int $purgingId = null;

    public string $purgeReason = '';

    // ── Bulk reason state (single-row uses the props above) ───────────────────

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
        $query = User::query()->appUsers();

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
            'email_verified' => [
                'label' => 'Email Verified',
                'type' => 'select',
                'options' => ['verified' => 'Verified', 'unverified' => 'Not verified'],
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'verified' => $q->whereNotNull('email_verified_at'),
                    'unverified' => $q->whereNull('email_verified_at'),
                    default => $q,
                },
            ],
            'social' => [
                'label' => 'Social',
                'type' => 'multi-select',
                'options' => ['google' => 'Google', 'apple' => 'Apple'],
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
                'label' => 'Total Users',
                'value' => fn () => User::appUsers()->count(),
                'icon' => 'users',
                'description' => 'All registered accounts',
            ],
            [
                'label' => 'Active',
                'value' => fn () => User::appUsers()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => 'Not banned',
            ],
            [
                'label' => 'Banned',
                'value' => fn () => User::appUsers()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => 'Banned accounts',
            ],
            [
                'label' => 'New This Month',
                'value' => fn () => User::appUsers()->whereMonth('registration_date', now()->month)
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
            'email_verified' => [
                'label' => 'Email',
                'type' => 'select',
                'options' => ['verified' => 'Verified', 'unverified' => 'Not verified'],
            ],
            'social' => [
                'label' => 'Social',
                'type' => 'multi-select',
                'options' => ['google' => 'Google', 'apple' => 'Apple'],
            ],
            'registered' => [
                'label' => 'Registered',
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

    // ── Single-row actions ────────────────────────────────────────────────────

    public function openBanDialog(int $userId): void
    {
        $this->authorize('users.ban');
        $this->banningUserId = $userId;
        $this->banReason = '';
        $this->dispatch('open-dialog-ban-user');
    }

    public function confirmBan(): void
    {
        $this->authorize('users.ban');

        $user = User::findOrFail($this->banningUserId);
        $reason = trim($this->banReason) ?: 'Banned by administrator.';
        $user->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
        ]);

        $this->logActivity(ActivityModule::User, ActivityAction::Banned, $user, ['ban_reason' => $reason]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess("User {$user->name} has been banned.");
    }

    public function unban(int $userId): void
    {
        $this->authorize('users.unban');

        $user = User::findOrFail($userId);
        $user->update(['banned_at' => null, 'ban_reason' => null]);

        $this->logActivity(ActivityModule::User, ActivityAction::Unbanned, $user);

        $this->toastSuccess("{$user->name} has been unbanned.");
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('users.delete');
        $this->deletingId = $userId;
        $this->dispatch('open-alert-dialog-delete-user');
    }

    public function delete(): void
    {
        $this->authorize('users.delete');

        $user = User::findOrFail($this->deletingId);
        $name = $user->name;
        $user->delete();

        $this->logActivity(ActivityModule::User, ActivityAction::Deleted, $user);

        $this->deletingId = null;
        $this->toastSuccess("{$name} has been deleted.");
    }

    public function confirmRestore(int $userId): void
    {
        $this->authorize('users.restore');
        $this->restoringId = $userId;
        $this->dispatch('open-alert-dialog-restore-user');
    }

    public function restore(): void
    {
        $this->authorize('users.restore');

        $user = User::withTrashed()->findOrFail($this->restoringId);
        $user->restore();

        $this->logActivity(ActivityModule::User, ActivityAction::Restored, $user);

        $this->restoringId = null;
        $this->toastSuccess("{$user->name} has been restored.");
    }

    public function confirmForceDelete(int $userId): void
    {
        $this->authorize('users.force-delete');
        $this->forceDeleteId = $userId;
        $this->dispatch('open-alert-dialog-force-delete-user');
    }

    public function forceDelete(): void
    {
        $this->authorize('users.force-delete');

        $user = User::withTrashed()->findOrFail($this->forceDeleteId);
        $name = $user->name;

        $this->logActivity(ActivityModule::User, ActivityAction::ForceDeleted, $user, ['user_id' => $user->id, 'name' => $name]);

        $user->forceDelete();

        $this->forceDeleteId = null;
        $this->toastSuccess("{$name} has been permanently deleted.");
    }

    // ── Account deletion (grace period) ───────────────────────────────────────

    public function openScheduleDeletionDialog(int $userId): void
    {
        $this->authorize('users.delete');
        $this->schedulingId = $userId;
        $this->deletionReason = '';
        $this->dispatch('open-dialog-schedule-deletion');
    }

    public function confirmScheduleDeletion(AccountDeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $user = User::query()->appUsers()->findOrFail($this->schedulingId);
        $deletions->requestByAdmin($user, trim($this->deletionReason) ?: null);

        $this->schedulingId = null;
        $this->deletionReason = '';
        $this->toastSuccess("{$user->name} is scheduled for deletion.");
    }

    public function stopDeletion(int $userId, AccountDeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $user = User::query()->appUsers()->findOrFail($userId);
        $deletions->cancelByAdmin($user);

        $this->toastSuccess("Deletion cancelled for {$user->name}.");
    }

    public function confirmInstantPurge(int $userId): void
    {
        $this->authorize('users.force-delete');
        $this->purgingId = $userId;
        $this->purgeReason = '';
        $this->dispatch('open-dialog-instant-purge');
    }

    public function instantPurge(AccountDeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $user = User::query()->appUsers()->findOrFail($this->purgingId);
        $name = $user->name;
        $deletions->instantPurgeByAdmin($user, trim($this->purgeReason) ?: null);

        $this->purgingId = null;
        $this->purgeReason = '';
        $this->toastSuccess("{$name} has been permanently deleted.");
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

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

    public function executeBulkScheduleDeletion(AccountDeletionService $deletions): void
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

    public function executeBulkStopDeletion(AccountDeletionService $deletions): void
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

    public function executeBulkInstantPurge(AccountDeletionService $deletions): void
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
        ]);
    }
}
