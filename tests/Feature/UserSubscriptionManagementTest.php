<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Users\Index;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    private function activePrice(): PlanPrice
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        return PlanPrice::factory()->for($plan)->create(['is_active' => true, 'amount' => 9.99]);
    }

    public function test_users_index_shows_plan_name_or_free(): void
    {
        $this->actingAsSuperAdmin();

        $subscribed = User::factory()->app()->create();
        $price = $this->activePrice();
        Subscription::factory()->for($subscribed)->for($price->plan)->for($price, 'planPrice')->create(['status' => 'active']);

        $free = User::factory()->app()->create();

        Livewire::test(Index::class)
            ->assertSee($price->plan->name)
            ->assertSee('Free');

        $this->assertNotNull($subscribed->fresh()->activeSubscription);
        $this->assertNull($free->fresh()->activeSubscription);
    }

    public function test_assigning_a_plan_to_an_unsubscribed_user_creates_a_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();

        Livewire::test(Show::class, ['user' => $user])
            ->set('assignPlanId', $price->plan_id)
            ->set('assignPriceId', $price->id)
            ->call('assignPlan');

        $subscription = $user->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame($price->plan_id, $subscription->plan_id);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $user->id,
            'subject_type' => User::class,
            'event' => 'assigned',
        ]);
    }

    public function test_changing_plan_upgrades_and_links_previous_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $oldPrice = $this->activePrice();
        $old = Subscription::factory()->for($user)->for($oldPrice->plan)->for($oldPrice, 'planPrice')->create(['status' => 'active']);

        $newPrice = $this->activePrice();

        Livewire::test(Show::class, ['user' => $user])
            ->set('assignPlanId', $newPrice->plan_id)
            ->set('assignPriceId', $newPrice->id)
            ->call('assignPlan');

        $new = $user->fresh()->activeSubscription;
        $this->assertNotNull($new);
        $this->assertSame($newPrice->plan_id, $new->plan_id);
        $this->assertSame($old->id, $new->previous_subscription_id);
        $this->assertSame('cancelled', $old->fresh()->status->value);
    }

    public function test_cancel_immediately_ends_access_right_away(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        Subscription::factory()->for($user)->for($price->plan)->for($price, 'planPrice')
            ->create(['status' => 'active', 'ends_at' => now()->addMonth()]);

        Livewire::test(Show::class, ['user' => $user])
            ->set('cancelReason', 'Requested refund')
            ->call('cancelImmediately');

        $this->assertNull($user->fresh()->activeSubscription);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $user->id,
            'event' => 'cancelled',
        ]);
    }

    public function test_cancel_at_period_end_keeps_access_until_ends_at(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        $sub = Subscription::factory()->for($user)->for($price->plan)->for($price, 'planPrice')
            ->create(['status' => 'active', 'ends_at' => now()->addMonth(), 'is_recurring' => true]);

        Livewire::test(Show::class, ['user' => $user])
            ->set('cancelReason', '')
            ->call('cancelAtPeriodEnd');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status->value);
        $this->assertFalse($sub->is_recurring);
        $this->assertTrue($sub->ends_at->isFuture());

        // Still counted as active (access continues) since ends_at hasn't passed.
        $this->assertNotNull($user->fresh()->activeSubscription);
    }

    public function test_reactivate_restores_a_cancelled_but_still_live_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        Subscription::factory()->for($user)->for($price->plan)->for($price, 'planPrice')->create([
            'status' => 'cancelled',
            'ends_at' => now()->addWeek(),
            'is_recurring' => false,
            'cancelled_by' => 'admin',
            'cancelled_reason' => 'Requested',
        ]);

        Livewire::test(Show::class, ['user' => $user])->call('reactivateSubscription');

        $subscription = $user->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status->value);
        $this->assertTrue($subscription->is_recurring);
        $this->assertNull($subscription->cancelled_by);
    }

    public function test_subscription_actions_require_users_manage_permission(): void
    {
        // Subscription management reuses users.manage (see Show::viewPermission()) rather
        // than a dedicated permission — so a staff member lacking it can't even mount the
        // profile page, let alone reach assignPlan(). Both gates fail at the same point.
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);

        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])->assertForbidden();

        $this->assertNull($user->fresh()->activeSubscription);
    }
}
