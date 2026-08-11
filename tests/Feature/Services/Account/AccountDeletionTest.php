<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Account\AccountDeletionScheduledNotification;
use App\Services\Account\DeletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DeletionService
    {
        return app(DeletionService::class);
    }

    public function test_user_can_request_and_cancel_their_own_deletion(): void
    {
        $user = User::factory()->app()->create();

        $this->service()->requestByUser($user, 'Leaving the app');

        $this->assertTrue($user->fresh()->isPendingDeletion());
        $this->assertSame('user', $user->fresh()->deletion_requested_by);

        $this->service()->cancelByUser($user->fresh());

        $this->assertFalse($user->fresh()->isPendingDeletion());
        $this->assertNull($user->fresh()->deletion_requested_by);
    }

    public function test_user_cannot_cancel_an_admin_initiated_deletion(): void
    {
        $user = User::factory()->app()->create();

        $this->service()->requestByAdmin($user, 'Policy violation');

        $this->expectException(AuthorizationException::class);

        $this->service()->cancelByUser($user->fresh());
    }

    public function test_admin_can_cancel_any_pending_deletion(): void
    {
        $user = User::factory()->app()->create();

        $this->service()->requestByUser($user, 'Changed my mind later');
        $this->service()->cancelByAdmin($user->fresh());

        $this->assertFalse($user->fresh()->isPendingDeletion());
    }

    public function test_scheduled_job_purges_accounts_past_the_grace_period(): void
    {
        config(['panel.account_deletion_grace_hours' => 24]);

        $expired = User::factory()->pendingDeletion('user', now()->subHours(25))->create();
        $withinGrace = User::factory()->pendingDeletion('user', now()->subHours(1))->create();

        $purged = $this->service()->purgeExpired();

        $this->assertSame(1, $purged);
        $this->assertNull(User::withTrashed()->find($expired->id), 'Expired account should be force-deleted');
        $this->assertNotNull($withinGrace->fresh(), 'Account still within grace should survive');
    }

    public function test_admin_instant_purge_skips_the_grace_period(): void
    {
        $user = User::factory()->app()->create();

        $this->service()->instantPurgeByAdmin($user, 'Immediate removal');

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    public function test_purge_is_idempotent(): void
    {
        $user = User::factory()->pendingDeletion('admin', now()->subDays(2))->create();

        $this->service()->purge($user, 'scheduled');
        // Running again on an already-destroyed model must not throw.
        $this->service()->purge($user, 'scheduled');

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    public function test_guests_never_enter_the_deletion_flow(): void
    {
        $guest = User::factory()->guest()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->requestByUser($guest);
    }

    public function test_admin_cannot_schedule_deletion_for_a_guest(): void
    {
        $guest = User::factory()->guest()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->requestByAdmin($guest);
    }

    public function test_user_initiated_request_sends_a_cancellable_deletion_notification(): void
    {
        Notification::fake();

        $user = User::factory()->app()->create();

        $this->service()->requestByUser($user, 'Leaving the app');

        Notification::assertSentTo(
            $user,
            AccountDeletionScheduledNotification::class,
            fn (AccountDeletionScheduledNotification $notification) => $notification->initiatedByAdmin === false
                && $notification->purgesAt->equalTo($user->fresh()->deletionPurgesAt())
        );
    }

    public function test_admin_initiated_request_sends_a_non_cancellable_deletion_notification(): void
    {
        Notification::fake();

        $user = User::factory()->app()->create();

        $this->service()->requestByAdmin($user, 'Policy violation');

        Notification::assertSentTo(
            $user,
            AccountDeletionScheduledNotification::class,
            fn (AccountDeletionScheduledNotification $notification) => $notification->initiatedByAdmin === true
                && $notification->purgesAt->equalTo($user->fresh()->deletionPurgesAt())
        );
    }

    public function test_scheduled_purge_ignores_guest_accounts(): void
    {
        // Even if a guest somehow carries a stale deletion timestamp, the
        // scheduler must never purge it.
        $guest = User::factory()->guest()->create([
            'deletion_requested_at' => now()->subDays(5),
            'deletion_requested_by' => 'user',
        ]);

        $purged = $this->service()->purgeExpired();

        $this->assertSame(0, $purged);
        $this->assertNotNull($guest->fresh());
    }
}
