<?php

namespace Tests\Feature\Services\Account;

use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Account\MergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccountMergeSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MergeService
    {
        return app(MergeService::class);
    }

    private function guest(): User
    {
        return User::factory()->guest()->create(['banned_at' => null]);
    }

    private function appUser(): User
    {
        return User::factory()->app()->create();
    }

    public function test_merge_with_no_active_subscriptions_leaves_destination_without_one(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertNull($destination->fresh()->activeSubscription);
    }

    public function test_only_guest_active_subscription_is_reassigned_and_preserved(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Stripe->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $guestSub->refresh();
        $this->assertSame($destination->id, $guestSub->user_id);
        $this->assertSame(SubscriptionStatus::Active, $guestSub->status);
        $this->assertSame($guestSub->id, $destination->fresh()->activeSubscription?->id);
    }

    public function test_only_app_active_subscription_is_kept_and_guest_history_is_still_reassigned(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create();
        $guestOldSub = Subscription::factory()->expired()->for($guest)->create();

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertSame($appSub->id, $destination->fresh()->activeSubscription?->id);
        $this->assertSame(SubscriptionStatus::Active, $appSub->fresh()->status);

        $guestOldSub->refresh();
        $this->assertSame($destination->id, $guestOldSub->user_id);
        $this->assertDatabaseHas('subscriptions', ['id' => $guestOldSub->id]);
    }

    public function test_both_active_same_provider_keeps_app_subscription_and_cancels_guests(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Stripe->value]);
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Stripe->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertSame($appSub->id, $destination->fresh()->activeSubscription?->id);

        $guestSub->refresh();
        $this->assertSame($destination->id, $guestSub->user_id);
        $this->assertSame(SubscriptionStatus::Cancelled, $guestSub->status);
        $this->assertSame(CancelledBy::System, $guestSub->cancelled_by);
        $this->assertNotNull($guestSub->cancelled_reason);
        // The subscription record itself must never be deleted, only cancelled.
        $this->assertDatabaseHas('subscriptions', ['id' => $guestSub->id]);
    }

    public function test_local_app_subscription_loses_to_an_external_guest_subscription(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Local->value]);
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Stripe->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $guestSub->refresh();
        $appSub->refresh();

        $this->assertSame($guestSub->id, $destination->fresh()->activeSubscription?->id);
        $this->assertSame(SubscriptionStatus::Active, $guestSub->status);
        $this->assertSame(SubscriptionStatus::Cancelled, $appSub->status);
        $this->assertSame(CancelledBy::System, $appSub->cancelled_by);
        $this->assertSame($appSub->id, $guestSub->previous_subscription_id);
    }

    public function test_external_app_subscription_beats_a_local_guest_subscription(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Stripe->value]);
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Local->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertSame($appSub->id, $destination->fresh()->activeSubscription?->id);
        $this->assertSame(SubscriptionStatus::Cancelled, $guestSub->fresh()->status);
    }

    public function test_both_local_keeps_the_app_subscription(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Local->value]);
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Local->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertSame($appSub->id, $destination->fresh()->activeSubscription?->id);
        $this->assertSame(SubscriptionStatus::Cancelled, $guestSub->fresh()->status);
    }

    public function test_different_non_local_providers_keeps_the_app_subscription(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        $appSub = Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::AppStore->value]);
        $guestSub = Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::PlayStore->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $this->assertSame($appSub->id, $destination->fresh()->activeSubscription?->id);
        $this->assertSame(SubscriptionStatus::Cancelled, $guestSub->fresh()->status);
    }

    public function test_cancelled_guest_subscription_is_logged_against_the_destination_not_the_guest(): void
    {
        $guest = $this->guest();
        $guestId = $guest->id;
        $destination = $this->appUser();
        Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Stripe->value]);
        Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::Stripe->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $row = Activity::where('event', 'cancelled')
            ->where('properties->type', 'subscription_cancelled')
            ->firstOrFail();

        $this->assertSame(User::class, $row->subject_type);
        $this->assertSame($destination->id, $row->subject_id);
        $this->assertNotSame($guestId, $row->subject_id);
    }

    public function test_merge_never_leaves_the_destination_with_more_than_one_active_subscription(): void
    {
        $guest = $this->guest();
        $destination = $this->appUser();
        Subscription::factory()->for($destination)->create(['provider' => PaymentProvider::Stripe->value]);
        Subscription::factory()->for($guest)->create(['provider' => PaymentProvider::AppStore->value]);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');

        $activeCount = Subscription::query()
            ->where('user_id', $destination->id)
            ->where('status', '!=', SubscriptionStatus::Failed->value)
            ->where(function ($query) {
                $query->where('ends_at', '>', now())
                    ->orWhere('grace_ends_at', '>', now());
            })
            ->count();

        $this->assertSame(1, $activeCount);
    }
}
