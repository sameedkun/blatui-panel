<?php

namespace App\Livewire\Admin\Management\Guests\Concerns;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Users\Concerns\HandlesUserRowActions;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Single-row guest actions (ban, delete, restore, force-delete) shared by the
 * Guests index and the guest profile page — mirrors
 * {@see HandlesUserRowActions}
 * so a ban from the profile header runs the exact same code (and writes the
 * exact same activity-log row) as a ban from the index.
 *
 * Guests never enter the app-user grace-period deletion flow, so there is no
 * scheduling/pending state here: Delete is a single, immediate, permanent
 * purge via {@see AccountDeletionService::purgeGuestByAdmin()}. Restore and
 * Force-Delete remain as a safety net for any guest that reaches a trashed
 * state some other way.
 *
 * Requires the using component to also use {@see LogsAdminActivity}
 * and {@see HasToast}.
 */
trait HandlesGuestRowActions
{
    public ?int $banningUserId = null;

    public string $banReason = '';

    public ?int $deletingId = null;

    public ?int $restoringId = null;

    public ?int $forceDeleteId = null;

    public function openBanDialog(int $userId): void
    {
        $this->authorize('guests.ban');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['active']);

        $this->banningUserId = $userId;
        $this->banReason = '';
        $this->dispatch('open-dialog-ban-user');
    }

    public function confirmBan(): void
    {
        $this->authorize('guests.ban');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->banningUserId);
        $this->assertLifecycleState($guest, ['active']);

        $reason = trim($this->banReason) ?: __('guests.defaults.ban_reason');
        $guest->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
        ]);

        $this->logActivity(ActivityModule::Guest, ActivityAction::Banned, $guest, ['ban_reason' => $reason]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess(__('guests.toasts.guest_banned', ['name' => $guest->name]));
    }

    public function unban(int $userId): void
    {
        $this->authorize('guests.unban');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['active']);

        $guest->update(['banned_at' => null, 'ban_reason' => null]);

        $this->logActivity(ActivityModule::Guest, ActivityAction::Unbanned, $guest);

        $this->toastSuccess(__('guests.toasts.guest_unbanned', ['name' => $guest->name]));
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('guests.delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['active']);

        $this->deletingId = $userId;
        $this->dispatch('open-alert-dialog-delete-user');
    }

    /**
     * Instant, on-the-spot delete — permanently purges the guest and all
     * related data in one step. Unlike the Users module, there is no
     * soft-delete stage to go through first.
     */
    public function delete(AccountDeletionService $deletions): void
    {
        $this->authorize('guests.delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->deletingId);
        $this->assertLifecycleState($guest, ['active']);

        $name = $guest->name;
        $deletions->purgeGuestByAdmin($guest);

        $this->deletingId = null;
        $this->toastSuccess(__('guests.toasts.guest_deleted', ['name' => $name]));
    }

    public function confirmRestore(int $userId): void
    {
        $this->authorize('guests.restore');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['trashed']);

        $this->restoringId = $userId;
        $this->dispatch('open-alert-dialog-restore-user');
    }

    public function restore(): void
    {
        $this->authorize('guests.restore');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->restoringId);
        $this->assertLifecycleState($guest, ['trashed']);

        $guest->restore();

        $this->logActivity(ActivityModule::Guest, ActivityAction::Restored, $guest);

        $this->restoringId = null;
        $this->toastSuccess(__('guests.toasts.guest_restored', ['name' => $guest->name]));
    }

    public function confirmForceDelete(int $userId): void
    {
        $this->authorize('guests.force-delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['trashed']);

        $this->forceDeleteId = $userId;
        $this->dispatch('open-alert-dialog-force-delete-user');
    }

    public function forceDelete(): void
    {
        $this->authorize('guests.force-delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertLifecycleState($guest, ['trashed']);

        $name = $guest->name;

        $this->logActivity(ActivityModule::Guest, ActivityAction::ForceDeleted, $guest, ['user_id' => $guest->id, 'name' => $name]);

        $guest->forceDelete();

        $this->forceDeleteId = null;
        $this->toastSuccess(__('guests.toasts.guest_permanently_deleted', ['name' => $name]));
    }

    /**
     * Reject an action attempted from a lifecycle state it doesn't apply to —
     * defense-in-depth behind the view's own state-gating of which buttons show.
     *
     * @param  array<int, string>  $allowed
     *
     * @throws AuthorizationException
     */
    private function assertLifecycleState(User $guest, array $allowed): void
    {
        $state = $guest->lifecycleState();

        if (! in_array($state, $allowed, true)) {
            throw new AuthorizationException(__('guests.errors.action_unavailable', [
                'state' => __("guests.lifecycle_states.{$state}"),
            ]));
        }
    }
}
