<?php

namespace App\Services\Account;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Models\User;
use App\Notifications\Account\AccountDeletionScheduledNotification;
use App\Support\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Single home for every account-deletion transition. Both the scheduled purge
 * job and the admin panel call into here — no deletion logic lives elsewhere.
 *
 * Deletion is a two-phase flow: a request marks the account (it stays live for
 * the grace period), then a purge permanently removes it. Guests never enter
 * this flow; it is exclusively for {@see UserType::App} accounts.
 */
class DeletionService
{
    /**
     * The user asks to delete their own account. The account stays live for the
     * grace period and the user may cancel until the purge runs.
     */
    public function requestByUser(User $user, ?string $reason = null): void
    {
        $this->assertAppUser($user);

        $this->markRequested($user, 'user', $reason);

        ActivityLogger::log(ActivityModule::User, ActivityAction::DeletionRequested, $user, [
            'initiated_by' => 'user',
            'reason' => $reason,
        ]);

        $user->notify(new AccountDeletionScheduledNotification($user->deletionPurgesAt(), initiatedByAdmin: false));
    }

    /**
     * An admin schedules the account for deletion. Same grace period, but the
     * user cannot cancel an admin-initiated request themselves.
     */
    public function requestByAdmin(User $user, ?string $reason = null): void
    {
        $this->assertAppUser($user);

        $this->markRequested($user, 'admin', $reason);

        ActivityLogger::log(ActivityModule::User, ActivityAction::DeletionRequested, $user, [
            'initiated_by' => 'admin',
            'reason' => $reason,
        ]);

        $user->notify(new AccountDeletionScheduledNotification($user->deletionPurgesAt(), initiatedByAdmin: true));
    }

    /**
     * The user cancels their own pending deletion. Only permitted when the
     * request was user-initiated — a user can never override an admin request.
     *
     * @throws AuthorizationException
     */
    public function cancelByUser(User $user): void
    {
        if ($user->deletion_requested_by !== 'user') {
            throw new AuthorizationException('This deletion request cannot be cancelled by the user.');
        }

        $this->clearRequest($user);

        ActivityLogger::log(ActivityModule::User, ActivityAction::DeletionCancelled, $user, [
            'initiated_by' => 'user',
        ]);
    }

    /**
     * An admin cancels a pending deletion regardless of who initiated it.
     */
    public function cancelByAdmin(User $user): void
    {
        $this->clearRequest($user);

        ActivityLogger::log(ActivityModule::User, ActivityAction::DeletionCancelled, $user, [
            'initiated_by' => 'admin',
        ]);
    }

    /**
     * Permanently destroy the account and its related data. Wrapped in a
     * transaction and safe to run more than once on the same account.
     *
     * @param  string  $initiatedBy  'scheduled' | 'admin_instant'
     */
    public function purge(User $user, string $initiatedBy): void
    {
        // Already purged — stay idempotent and skip a duplicate audit entry.
        if (! $user->exists) {
            return;
        }

        // Snapshot before destruction — nothing survives the forceDelete.
        $snapshot = [
            'id' => $user->id,
            'email' => $user->email,
            'type' => $user->type->value,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        // The scheduled sweep runs with no auth() session, so force a null
        // (system) causer and an explicit Scheduler context rather than trusting
        // the ambient runtime; admin instant-purge keeps the acting admin and
        // auto-detects its (Admin) context.
        $causer = $initiatedBy === 'scheduled' ? null : auth()->user();
        $context = $initiatedBy === 'scheduled' ? ActivityContext::Scheduler : null;
        $module = $user->type === UserType::Guest ? ActivityModule::Guest : ActivityModule::User;

        DB::transaction(function () use ($user): void {
            $this->deleteRelatedData($user);

            if ($user->exists) {
                $user->forceDelete();
            }
        });

        ActivityLogger::log($module, ActivityAction::Purged, null, [
            'initiated_by' => $initiatedBy,
            'snapshot' => $snapshot,
        ], causer: $causer, context: $context);
    }

    /**
     * Admin permanently deletes an account immediately, skipping the grace period.
     */
    public function instantPurgeByAdmin(User $user, ?string $reason = null): void
    {
        $this->assertAppUser($user);

        if ($reason !== null && $reason !== '') {
            $user->forceFill(['deletion_reason' => $reason])->save();
        }

        $this->purge($user, 'admin_instant');
    }

    /**
     * Admin permanently deletes a guest account immediately. Guests never enter
     * the grace-period flow at all, so this skips straight to the purge — no
     * request/cancel phase, no reason column to persist.
     */
    public function purgeGuestByAdmin(User $guest): void
    {
        $this->assertGuestUser($guest);

        $this->purge($guest, 'admin_instant');
    }

    /**
     * Purge every app account whose grace period has elapsed. Called hourly by
     * the scheduler; returns the number of accounts purged.
     */
    public function purgeExpired(): int
    {
        $cutoff = now()->subHours($this->graceHours());
        $purged = 0;

        User::query()
            ->appUsers()
            ->pendingDeletion()
            ->where('deletion_requested_at', '<', $cutoff)
            ->chunkById(100, function ($users) use (&$purged): void {
                foreach ($users as $user) {
                    $this->purge($user, 'scheduled');
                    $purged++;
                }
            });

        return $purged;
    }

    protected function markRequested(User $user, string $by, ?string $reason): void
    {
        $user->forceFill([
            'deletion_requested_at' => now(),
            'deletion_requested_by' => $by,
            'deletion_reason' => $reason,
        ])->save();
    }

    protected function clearRequest(User $user): void
    {
        $user->forceFill([
            'deletion_requested_at' => null,
            'deletion_requested_by' => null,
            'deletion_reason' => null,
        ])->save();
    }

    /**
     * Remove rows owned by the account. Each table is guarded so the pipeline
     * stays idempotent and tolerates data that is missing or already deleted.
     *
     * `subscriptions` and `user_devices` aren't listed here — both have a
     * `user_id` FK that's `cascadeOnDelete()` at the DB level, so the
     * forceDelete() below already removes those rows; explicit cleanup would
     * be redundant. (`subscription_receipts` cascades transitively off
     * `subscriptions` the same way.)
     *
     * `blocked_ips` IS listed here, unlike those two — its `user_id` FK is
     * `restrictOnDelete()`, not `cascadeOnDelete()` (InnoDB refuses a cascading
     * action on a column a stored generated column depends on — see the
     * migration), so this explicit delete is what actually prevents the
     * subsequent forceDelete() from being blocked by that FK.
     *
     * `personal_access_tokens` and `sessions` are also listed here — neither
     * has a real FK constraint at all (`personal_access_tokens.tokenable` is
     * a polymorphic `morphs()` column, `sessions.user_id` is a plain nullable
     * column), so nothing at the DB level cleans them up; without this they'd
     * be orphaned forever.
     *
     * The avatar file itself is deleted here too — it lives on disk, not in
     * a DB row, so nothing about the forceDelete() below would ever remove
     * it on its own.
     */
    protected function deleteRelatedData(User $user): void
    {
        if (Schema::hasTable('blocked_ips')) {
            DB::table('blocked_ips')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if ($user->avatar) {
            Storage::delete($user->avatar);
        }
    }

    protected function graceHours(): int
    {
        return (int) config('panel.account_deletion_grace_hours', 24);
    }

    protected function assertAppUser(User $user): void
    {
        if ($user->type !== UserType::App) {
            throw new InvalidArgumentException('The account deletion flow is only available to app users.');
        }
    }

    protected function assertGuestUser(User $user): void
    {
        if ($user->type !== UserType::Guest) {
            throw new InvalidArgumentException('This action is only available to guest accounts.');
        }
    }
}
