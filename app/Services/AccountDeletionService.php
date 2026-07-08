<?php

namespace App\Services;

use App\Enum\UserType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Single home for every account-deletion transition. Both the scheduled purge
 * job and the admin panel call into here — no deletion logic lives elsewhere.
 *
 * Deletion is a two-phase flow: a request marks the account (it stays live for
 * the grace period), then a purge permanently removes it. Guests never enter
 * this flow; it is exclusively for {@see UserType::App} accounts.
 */
class AccountDeletionService
{
    /**
     * The user asks to delete their own account. The account stays live for the
     * grace period and the user may cancel until the purge runs.
     */
    public function requestByUser(User $user, ?string $reason = null): void
    {
        $this->assertAppUser($user);

        $this->markRequested($user, 'user', $reason);

        // TODO: audit log hook — deletion_requested (by user), reason.
    }

    /**
     * An admin schedules the account for deletion. Same grace period, but the
     * user cannot cancel an admin-initiated request themselves.
     */
    public function requestByAdmin(User $user, ?string $reason = null): void
    {
        $this->assertAppUser($user);

        $this->markRequested($user, 'admin', $reason);

        // TODO: audit log hook — deletion_requested (by admin), reason.
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

        // TODO: audit log hook — deletion_cancelled (by user).
    }

    /**
     * An admin cancels a pending deletion regardless of who initiated it.
     */
    public function cancelByAdmin(User $user): void
    {
        $this->clearRequest($user);

        // TODO: audit log hook — deletion_cancelled (by admin).
    }

    /**
     * Permanently destroy the account and its related data. Wrapped in a
     * transaction and safe to run more than once on the same account.
     *
     * @param  string  $initiatedBy  'scheduled' | 'admin_instant'
     */
    public function purge(User $user, string $initiatedBy): void
    {
        // TODO: audit log hook — account_purged. Snapshot id/email/type/created_at
        // and $initiatedBy BEFORE the account is destroyed, since nothing survives.

        DB::transaction(function () use ($user): void {
            $this->deleteRelatedData($user);

            if ($user->exists) {
                $user->forceDelete();
            }
        });
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
     * NOTE: subscriptions / devices / personal_access_tokens tables do not exist
     * in the schema yet — the guards make this a no-op until they are added.
     */
    protected function deleteRelatedData(User $user): void
    {
        foreach (['subscriptions', 'devices'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
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
}
