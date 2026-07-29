<?php

namespace Tests\Feature;

use App\Enum\BillingInterval;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Livewire\Admin\Management\Guests\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestSubscriptionManagementTest extends TestCase
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

    public function test_subscription_enum_labels_use_the_active_locale(): void
    {
        App::setLocale('tr');

        $this->assertSame('Aktif', SubscriptionStatus::Active->label());
        $this->assertSame('Ay', BillingInterval::Month->label());
        $this->assertSame('Yerel', PaymentProvider::Local->label());
        $this->assertSame('Yönetici', CancelledBy::Admin->label());
    }

    public function test_assigning_a_plan_to_an_unsubscribed_guest_creates_a_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create();
        $price = $this->activePrice();

        Livewire::test(Show::class, ['user' => $guest])
            ->set('assignPlanId', $price->plan_id)
            ->set('assignPriceId', $price->id)
            ->call('assignPlan');

        $subscription = $guest->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame($price->plan_id, $subscription->plan_id);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $guest->id,
            'subject_type' => User::class,
            'event' => 'assigned',
        ]);
    }

    public function test_cancel_immediately_ends_access_right_away(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create();
        $price = $this->activePrice();
        Subscription::factory()->for($guest)->for($price->plan)->for($price, 'planPrice')
            ->create(['status' => 'active', 'ends_at' => now()->addMonth()]);

        Livewire::test(Show::class, ['user' => $guest])
            ->set('cancelReason', 'Requested refund')
            ->call('cancelImmediately');

        $this->assertNull($guest->fresh()->activeSubscription);
    }

    public function test_cancel_at_period_end_keeps_access_until_ends_at(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create();
        $price = $this->activePrice();
        $sub = Subscription::factory()->for($guest)->for($price->plan)->for($price, 'planPrice')
            ->create(['status' => 'active', 'ends_at' => now()->addMonth(), 'is_recurring' => true]);

        Livewire::test(Show::class, ['user' => $guest])
            ->set('cancelReason', '')
            ->call('cancelAtPeriodEnd');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status->value);
        $this->assertFalse($sub->is_recurring);
        $this->assertTrue($sub->ends_at->isFuture());
        $this->assertNotNull($guest->fresh()->activeSubscription);
    }

    public function test_reactivate_restores_a_cancelled_but_still_live_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create();
        $price = $this->activePrice();
        Subscription::factory()->for($guest)->for($price->plan)->for($price, 'planPrice')->create([
            'status' => 'cancelled',
            'ends_at' => now()->addWeek(),
            'is_recurring' => false,
            'cancelled_by' => 'admin',
            'cancelled_reason' => 'Requested',
        ]);

        Livewire::test(Show::class, ['user' => $guest])->call('reactivateSubscription');

        $subscription = $guest->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status->value);
        $this->assertFalse($subscription->is_recurring);
    }

    public function test_subscription_actions_require_guests_manage_permission(): void
    {
        // Staff with no roles/permissions at all — lacks guests.manage.
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);

        $guest = User::factory()->guest()->create();
        $price = $this->activePrice();

        Livewire::test(Show::class, ['user' => $guest])
            ->set('assignPlanId', $price->plan_id)
            ->set('assignPriceId', $price->id)
            ->call('assignPlan')
            ->assertForbidden();

        $this->assertNull($guest->fresh()->activeSubscription);
    }
}
