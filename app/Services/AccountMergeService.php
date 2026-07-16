<?php

namespace App\Services;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Merges a guest's identity into an existing app account. The destination
 * account survives and is what gets logged into; the guest row is disposed
 * of. Historical activity_log rows are left untouched — a single `merged`
 * event provides the linkage rather than rewriting old rows.
 */
class AccountMergeService
{
    public function mergeFromProvider(User $guest, User $destination, string $provider, string $providerId): User
    {
        $this->assertGuestUser($guest);
        $this->assertDestinationIsAppUser($destination);

        $column = "{$provider}_id";
        if ($destination->{$column} !== $providerId) {
            $destination->forceFill([$column => $providerId])->save();
        }

        ActivityLogger::log(ActivityModule::User, ActivityAction::Merged, $destination, [
            'guest_id' => $guest->id,
            'provider' => $provider,
            'initiated_by' => 'self',
        ]);

        $this->finish($guest, $destination);

        return $destination;
    }

    /**
     * Admin-initiated merge — requires an explicit reason since there's no
     * provider proof backing this one; it's an admin's judgment call and
     * must be traceable as such.
     */
    public function mergeByAdmin(User $guest, User $destination, string $reason): User
    {
        $this->assertGuestUser($guest);
        $this->assertDestinationIsAppUser($destination);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for an admin-initiated merge.');
        }

        ActivityLogger::log(ActivityModule::User, ActivityAction::Merged, $destination, [
            'guest_id' => $guest->id,
            'initiated_by' => 'admin',
            'admin_id' => Auth::id(),
            'reason' => $reason,
        ]);

        $this->finish($guest, $destination);

        return $destination;
    }

    private function finish(User $guest, User $destination): void
    {
        $this->migrateRelatedData($guest, $destination);
        $guest->forceDelete();
    }

    /**
     * TODO: once devices/subscriptions tables exist, reassign owned rows
     * from $guest->id to $destination->id here (Schema::hasTable-guarded,
     * same pattern as AccountDeletionService).
     */
    protected function migrateRelatedData(User $guest, User $destination): void
    {
        //
    }

    private function assertGuestUser(User $guest): void
    {
        if ($guest->type !== UserType::Guest) {
            throw new InvalidArgumentException('Only guest accounts can be merged.');
        }
    }

    private function assertDestinationIsAppUser(User $destination): void
    {
        if ($destination->type !== UserType::App) {
            throw new InvalidArgumentException('The merge destination must be an app user.');
        }
    }
}
