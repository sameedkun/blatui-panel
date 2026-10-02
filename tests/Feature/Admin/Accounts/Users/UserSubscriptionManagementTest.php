<?php

namespace Tests\Feature\Admin\Accounts\Users;

use App\Enum\SubscriptionSource;
use App\Livewire\Admin\Management\Users\Index;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
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

    public function test_an_admin_assignment_is_a_free_grant_recording_who_and_why_with_no_transaction(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();

        Livewire::test(Show::class, ['user' => $user])
            ->set('assignPlanId', $price->plan_id)
            ->set('assignPriceId', $price->id)
            ->set('assignReason', '  Customer support compensation  ')
            ->call('assignPlan')
            ->assertHasNoErrors();

        $subscription = $user->fresh()->activeSubscription;
        $this->assertSame(SubscriptionSource::Admin, $subscription->source);
        $this->assertSame($admin->id, $subscription->granted_by);
        $this->assertSame('Customer support compensation', $subscription->grant_reason);
        $this->assertSame(0, $subscription->transactions()->count(), 'no money changed hands, so no transaction');
        $this->assertSame([], $subscription->netPaid());

        $activity = Activity::forSubject($user)->where('event', 'assigned')->sole();
        $this->assertSame('admin', $activity->properties['source']);
        $this->assertSame('Customer support compensation', $activity->properties['reason']);
        $this->assertArrayNotHasKey('amount', $activity->properties->all());
    }

    public function test_a_grant_reason_longer_than_the_column_is_rejected(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();

        Livewire::test(Show::class, ['user' => $user])
            ->set('assignPlanId', $price->plan_id)
            ->set('assignPriceId', $price->id)
            ->set('assignReason', str_repeat('x', 256))
            ->call('assignPlan')
            ->assertHasErrors(['assignReason' => 'max']);

        $this->assertSame(0, Subscription::count());
    }

    public function test_a_purchase_never_carries_grant_metadata(): void
    {
        $admin = User::factory()->create(['type' => 'staff']);
        $user = User::factory()->app()->create();

        $subscription = app(SubscriptionService::class)->subscribe($user, $this->activePrice(), source: SubscriptionSource::Purchase, grantedBy: $admin, grantReason: 'ignored');

        $this->assertSame(SubscriptionSource::Purchase, $subscription->source);
        $this->assertNull($subscription->granted_by);
        $this->assertNull($subscription->grant_reason);
    }

    /** A live paid row with `$daysLeft` of its billing period remaining. */
    private function paidSubscription(User $user, PlanPrice $price, int $daysLeft): Subscription
    {
        return Subscription::factory()->for($user)->for($price->plan)->for($price, 'planPrice')->create([
            'status' => 'active',
            'starts_at' => now()->subDays(90),
            'ends_at' => now()->addDays($daysLeft),
        ]);
    }

    public function test_upgrade_credit_comes_from_the_current_periods_charge_not_every_renewal(): void
    {
        $this->freezeSecond();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        $days = $price->billingDurationInDays();
        $current = $this->paidSubscription($user, $price, intdiv($days, 2));

        foreach ([90, 60, 30] as $ago) {
            SubscriptionTransaction::factory()->for($current)->amount(999)->renewal()->create(['purchased_at' => now()->subDays($ago)]);
        }

        $proration = app(SubscriptionService::class)->prorationCredit($current, 'USD');

        $this->assertNull($proration['skipped']);
        $this->assertSame('USD', $proration['credit']->currency);
        $this->assertSame(Money::ofMinor(999, 'USD')->multipliedBy(intdiv($days, 2), $days)->minor, $proration['credit']->minor);
    }

    public function test_upgrade_credit_is_net_of_refunds_on_the_current_charge(): void
    {
        $this->freezeSecond();
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        $days = $price->billingDurationInDays();
        $current = $this->paidSubscription($user, $price, $days);

        $charge = SubscriptionTransaction::factory()->for($current)->amount(999)->create(['purchased_at' => now()]);
        SubscriptionTransaction::factory()->for($current)->amount(500)->refund()->create(['related_transaction_id' => $charge->id]);

        $this->assertSame(499, app(SubscriptionService::class)->prorationCredit($current, 'USD')['credit']->minor);
    }

    public function test_upgrade_credit_never_compares_amounts_in_different_currencies(): void
    {
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        $current = $this->paidSubscription($user, $price, 10);
        SubscriptionTransaction::factory()->for($current)->amount(899, 'EUR')->create(['purchased_at' => now()]);

        $proration = app(SubscriptionService::class)->prorationCredit($current, 'USD');

        $this->assertNull($proration['credit']);
        $this->assertSame('currency_mismatch', $proration['skipped']);
    }

    public function test_a_cross_currency_upgrade_records_why_no_credit_was_given(): void
    {
        $user = User::factory()->app()->create();
        $current = $this->paidSubscription($user, $this->activePrice(), 10);
        SubscriptionTransaction::factory()->for($current)->amount(899, 'EUR')->create(['purchased_at' => now()]);
        $usd = $this->activePrice();

        $new = app(SubscriptionService::class)->upgrade($user, $usd);

        $this->assertSame(['credit' => 0, 'credit_skipped' => 'currency_mismatch', 'paid_currency' => 'EUR'], array_intersect_key(
            $new->proration_meta,
            array_flip(['credit', 'credit_skipped', 'paid_currency']),
        ));

        $activity = Activity::forSubject($user)->where('properties->type', 'subscription_upgraded')->sole();
        $this->assertSame('currency_mismatch', $activity->properties['credit_skipped']);
    }

    public function test_a_grant_or_lapsed_period_earns_no_upgrade_credit(): void
    {
        $user = User::factory()->app()->create();
        $price = $this->activePrice();
        $service = app(SubscriptionService::class);

        $grant = Subscription::factory()->granted()->for($user)->for($price->plan)->for($price, 'planPrice')->create(['ends_at' => now()->addMonth()]);
        $this->assertSame(['credit' => null, 'skipped' => 'no_payment'], $service->prorationCredit($grant, 'USD'));

        $lapsed = $this->paidSubscription($user, $price, 0);
        $lapsed->update(['ends_at' => now()->subDay()]);
        SubscriptionTransaction::factory()->for($lapsed)->amount(999)->create(['purchased_at' => now()->subMonth()]);
        $this->assertSame(['credit' => null, 'skipped' => 'period_ended'], $service->prorationCredit($lapsed, 'USD'));
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
        $this->assertFalse($subscription->is_recurring);
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
