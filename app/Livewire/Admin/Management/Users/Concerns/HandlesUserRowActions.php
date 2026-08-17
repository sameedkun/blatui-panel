<?php

namespace App\Livewire\Admin\Management\Users\Concerns;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Single-row user actions (ban, delete, restore, force-delete, and the
 * grace-period deletion flow) shared by the Users index and the user profile
 * page. Extracting them here is what keeps the two in lockstep: a ban from the
 * profile header runs the exact same code — and writes the exact same
 * activity-log row — as a ban from the index.
 *
 * Every action is also state-gated: an account may only be acted on from the
 * lifecycle state(s) it applies to (active / pending deletion / trashed),
 * rejected via {@see assertLifecycleState} regardless of whether the
 * triggering UI hid the button. This is defense-in-depth for the profile
 * header, which surfaces different actions per state — the index already
 * scopes its row actions per status tab, so this only tightens it there.
 *
 * Requires the using component to also use {@see LogsAdminActivity}
 * and {@see HasToast}. The matching dialogs live in
 * the shared users/partials/single-row-dialogs.blade.php partial.
 */
trait HandlesUserRowActions
{
    public ?int $banningUserId = null;

    public string $banReason = '';

    public ?int $deletingId = null;

    public ?int $restoringId = null;

    public ?int $forceDeleteId = null;

    public ?int $schedulingId = null;

    public string $deletionReason = '';

    public ?int $purgingId = null;

    public string $purgeReason = '';

    public function openBanDialog(int $userId): void
    {
        $this->authorize('users.ban');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['active', 'pending']);

        $this->banningUserId = $userId;
        $this->banReason = '';
        $this->dispatch('open-dialog-ban-user');
    }

    public function confirmBan(): void
    {
        $this->authorize('users.ban');

        $user = User::withTrashed()->findOrFail($this->banningUserId);
        $this->assertLifecycleState($user, ['active', 'pending']);

        $reason = trim($this->banReason) ?: __('users.defaults.ban_reason');
        $user->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
        ]);

        $this->logActivity(ActivityModule::User, ActivityAction::Banned, $user, ['ban_reason' => $reason]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess(__('users.toasts.user_banned', ['name' => $user->name]));
    }

    public function unban(int $userId): void
    {
        $this->authorize('users.unban');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['active', 'pending']);

        $user->update(['banned_at' => null, 'ban_reason' => null]);

        $this->logActivity(ActivityModule::User, ActivityAction::Unbanned, $user);

        $this->toastSuccess(__('users.toasts.user_unbanned', ['name' => $user->name]));
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('users.delete');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['active']);

        $this->deletingId = $userId;
        $this->dispatch('open-alert-dialog-delete-user');
    }

    public function delete(): void
    {
        $this->authorize('users.delete');

        $user = User::withTrashed()->findOrFail($this->deletingId);
        $this->assertLifecycleState($user, ['active']);

        $name = $user->name;
        $user->delete();

        $this->logActivity(ActivityModule::User, ActivityAction::Deleted, $user);

        $this->deletingId = null;
        $this->toastSuccess(__('users.toasts.user_deleted', ['name' => $name]));
    }

    public function confirmRestore(int $userId): void
    {
        $this->authorize('users.restore');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['trashed']);

        $this->restoringId = $userId;
        $this->dispatch('open-alert-dialog-restore-user');
    }

    public function restore(): void
    {
        $this->authorize('users.restore');

        $user = User::withTrashed()->findOrFail($this->restoringId);
        $this->assertLifecycleState($user, ['trashed']);

        $user->restore();

        $this->logActivity(ActivityModule::User, ActivityAction::Restored, $user);

        $this->restoringId = null;
        $this->toastSuccess(__('users.toasts.user_restored', ['name' => $user->name]));
    }

    public function confirmForceDelete(int $userId): void
    {
        $this->authorize('users.force-delete');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['trashed']);

        $this->forceDeleteId = $userId;
        $this->dispatch('open-alert-dialog-force-delete-user');
    }

    public function forceDelete(DeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $user = User::withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertLifecycleState($user, ['trashed']);

        $name = $user->name;

        $this->logActivity(ActivityModule::User, ActivityAction::ForceDeleted, $user, ['user_id' => $user->id, 'name' => $name]);

        $deletions->forceDeleteRecord($user);

        $this->forceDeleteId = null;
        $this->toastSuccess(__('users.toasts.user_permanently_deleted', ['name' => $name]));
    }

    public function openScheduleDeletionDialog(int $userId): void
    {
        $this->authorize('users.delete');

        $user = User::withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['active']);

        $this->schedulingId = $userId;
        $this->deletionReason = '';
        $this->dispatch('open-dialog-schedule-deletion');
    }

    public function confirmScheduleDeletion(DeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $user = User::query()->appUsers()->withTrashed()->findOrFail($this->schedulingId);
        $this->assertLifecycleState($user, ['active']);

        $deletions->requestByAdmin($user, trim($this->deletionReason) ?: null);

        $this->schedulingId = null;
        $this->deletionReason = '';
        $this->toastSuccess(__('users.toasts.user_scheduled_deletion', ['name' => $user->name]));
    }

    public function stopDeletion(int $userId, DeletionService $deletions): void
    {
        $this->authorize('users.delete');

        $user = User::query()->appUsers()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['pending']);

        $deletions->cancelByAdmin($user);

        $this->toastSuccess(__('users.toasts.user_deletion_cancelled', ['name' => $user->name]));
    }

    public function confirmInstantPurge(int $userId): void
    {
        $this->authorize('users.force-delete');

        $user = User::query()->appUsers()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($user, ['pending']);

        $this->purgingId = $userId;
        $this->purgeReason = '';
        $this->dispatch('open-dialog-instant-purge');
    }

    public function instantPurge(DeletionService $deletions): void
    {
        $this->authorize('users.force-delete');

        $user = User::query()->appUsers()->withTrashed()->findOrFail($this->purgingId);
        $this->assertLifecycleState($user, ['pending']);

        $name = $user->name;
        $deletions->instantPurgeByAdmin($user, trim($this->purgeReason) ?: null);

        $this->purgingId = null;
        $this->purgeReason = '';
        $this->toastSuccess(__('users.toasts.user_permanently_deleted', ['name' => $name]));
    }

    /**
     * Reject an action attempted from a lifecycle state it doesn't apply to —
     * defense-in-depth behind the view's own state-gating of which buttons show.
     *
     * @param  array<int, string>  $allowed
     *
     * @throws AuthorizationException
     */
    private function assertLifecycleState(User $user, array $allowed): void
    {
        $state = $user->lifecycleState();

        if (! in_array($state, $allowed, true)) {
            throw new AuthorizationException(__('users.errors.action_unavailable', ['state' => $state]));
        }
    }
}
