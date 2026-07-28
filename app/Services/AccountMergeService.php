<?php

namespace App\Services;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
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
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

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
     * TODO: reassign the guest's owned `user_devices` rows to $destination
     * here too, once device migration on merge is needed (same pattern as
     * the subscription reassignment below).
     */
    protected function migrateRelatedData(User $guest, User $destination): void
    {
        $this->migrateSubscriptions($guest, $destination);
    }

    /**
     * Reassign every subscription the guest owns to the destination account
     * before the guest row is force-deleted — `subscriptions.user_id`
     * cascade-deletes on the guest's removal, and history must survive.
     *
     * Conflict resolution only cares about each side's *active* subscription
     * (if any). The destination account is canonical, with one exception: a
     * `local` (admin-assigned/no real gateway) app subscription always loses
     * to a real guest subscription from an external provider, since that
     * reflects an actual payment relationship the app's local one doesn't.
     * Every other combination keeps the app user's subscription and cancels
     * the guest's.
     */
    private function migrateSubscriptions(User $guest, User $destination): void
    {
        $guestActive = $guest->activeSubscription;
        $appActive = $destination->activeSubscription;

        // Reassign every guest subscription (active or historical) up front,
        // so any cancellation logged below attributes to the destination —
        // not the guest, who is about to be force-deleted.
        $guest->subscriptions()->update([
            'user_id' => $destination->id,
        ]);

        // The bulk update above is a query-builder call — it doesn't touch
        // the $guestActive instance already loaded into memory, so refresh
        // it to pick up the new user_id before anything reads ->user off it.
        $guestActive?->refresh();

        // No active subscription on either account.
        if (! $guestActive && ! $appActive) {
            return;
        }

        // Only guest has an active subscription — it's now reassigned and
        // simply becomes the destination's active subscription.
        if ($guestActive && ! $appActive) {
            return;
        }

        // Only app user has an active subscription — keep it unchanged.
        if (! $guestActive && $appActive) {
            return;
        }

        // Both have active subscriptions.
        $guestWins = $appActive->provider === PaymentProvider::Local
            && $guestActive->provider !== PaymentProvider::Local;

        if ($guestWins) {
            // Guest's external subscription replaces the app's local subscription.
            if ($guestActive->previous_subscription_id === null) {
                $guestActive->update([
                    'previous_subscription_id' => $appActive->id,
                ]);
            }

            $this->subscriptions->cancelSubscription(
                $appActive,
                CancelledBy::System,
                'Cancelled due to account merge',
                true
            );
        } else {
            // App subscription remains canonical.
            $this->subscriptions->cancelSubscription(
                $guestActive,
                CancelledBy::System,
                'Cancelled due to account merge',
                true
            );
        }
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
