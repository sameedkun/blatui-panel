<?php

namespace Tests\Feature\Services\Subscription;

use App\Enum\PaymentProvider;
use App\Exceptions\StoreManagedSubscriptionException;
use App\Livewire\Admin\Management\Guests\Show as GuestShow;
use App\Livewire\Admin\Management\Users\Show as UserShow;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\Account\DeletionService;
use App\Services\Subscription\SubscriptionService;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Financial history survives account deletion: subscriptions and their
 * transactions are detached from a purged user (user_id → NULL), never
 * deleted. Ownerless money stays in financial reporting and out of
 * customer metrics.
 */
class SubscriptionRetentionTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $range;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-28 12:00:00'));
        $this->range = DateRange::preset('30d');
    }

    private function paidSubscription(User $user, int $minor = 1000, string $provider = 'local'): Subscription
    {
        $price = PlanPrice::factory()->for(Plan::factory())->create(['amount' => $minor / 100, 'billing_interval' => 'month', 'billing_period' => 1]);
        $subscription = Subscription::factory()->for($user)->create([
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
            'provider' => $provider,
            'starts_at' => '2026-09-10',
            'ends_at' => '2026-10-10',
        ]);
        SubscriptionTransaction::factory()->for($subscription)->amount($minor)->create(['provider' => $provider, 'purchased_at' => '2026-09-10']);

        return $subscription;
    }

    public function test_purging_a_user_keeps_their_subscriptions_and_transactions_detached(): void
    {
        $user = User::factory()->app()->create();
        $subscription = $this->paidSubscription($user);
        $revenueBefore = app(RevenueMetrics::class)->revenue($this->range);

        app(DeletionService::class)->purge($user, 'admin', causer: null);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertNull($subscription->refresh()->user_id);
        $this->assertSame(1, $subscription->transactions()->count());
        $this->assertSame($revenueBefore, app(RevenueMetrics::class)->revenue($this->range), 'past revenue does not change');
    }

    public function test_force_deleting_a_trashed_user_keeps_the_ledger(): void
    {
        $user = User::factory()->app()->create();
        $subscription = $this->paidSubscription($user);
        $user->delete();

        app(DeletionService::class)->forceDeleteRecord($user);

        $this->assertNull($subscription->refresh()->user_id);
        $this->assertSame(1, SubscriptionTransaction::count());
    }

    public function test_ownerless_money_counts_as_revenue_but_not_towards_customer_metrics(): void
    {
        $revenue = app(RevenueMetrics::class);
        $subscriptions = app(SubscriptionMetrics::class);
        $this->paidSubscription(User::factory()->app()->create(), 1000);
        $ownerless = $this->paidSubscription(User::factory()->app()->create(), 3000);
        $ownerless->user->forceDelete();

        $this->assertNull($ownerless->refresh()->user_id);
        $this->assertSame(40.0, $revenue->revenue($this->range), 'all money is revenue');
        $this->assertSame(40.0, $revenue->mrr(), 'recurring money is recurring revenue');
        $this->assertSame(1, $revenue->payingCustomers($this->range));
        $this->assertSame(10.0, $revenue->arpu($this->range), "revenue per customer only uses customers' money");
        $this->assertSame(1, $revenue->liveCustomersAt(Date::now())->count());
        $this->assertSame(1, $subscriptions->live());
        $this->assertSame(1, $subscriptions->started($this->range));
        $this->assertSame(1, $subscriptions->paidAt(Date::now()), 'churn is measured against customers only');
    }

    public function test_admins_cannot_assign_a_plan_over_a_store_billed_subscription(): void
    {
        $user = User::factory()->app()->create();
        $store = $this->paidSubscription($user, provider: PaymentProvider::AppStore->value);

        $this->expectException(StoreManagedSubscriptionException::class);

        try {
            app(SubscriptionService::class)->subscribe($user, PlanPrice::factory()->create());
        } finally {
            $this->assertSame('active', $store->refresh()->status->value, 'the store row is untouched');
            $this->assertSame(1, $user->subscriptions()->count());
        }
    }

    public function test_admins_cannot_upgrade_over_a_store_billed_subscription(): void
    {
        $user = User::factory()->app()->create();
        $this->paidSubscription($user, provider: PaymentProvider::PlayStore->value);

        $this->expectException(StoreManagedSubscriptionException::class);

        app(SubscriptionService::class)->upgrade($user, PlanPrice::factory()->create());
    }

    public function test_the_profile_pages_refuse_a_plan_change_over_a_store_billed_subscription(): void
    {
        $admin = User::factory()->create(['type' => 'staff']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);
        $price = PlanPrice::factory()->for(Plan::factory()->create(['is_active' => true]))->create(['is_active' => true]);

        foreach ([[User::factory()->app()->create(), UserShow::class, 'users'], [User::factory()->guest()->create(), GuestShow::class, 'guests']] as [$owner, $component, $lang]) {
            $store = $this->paidSubscription($owner, provider: PaymentProvider::AppStore->value);

            Livewire::test($component, ['user' => $owner])
                ->call('openAssignPlanDialog')
                ->assertDispatched('toast', type: 'error')
                ->assertNotDispatched('open-dialog-assign-plan')
                ->set('assignPlanId', $price->plan_id)
                ->set('assignPriceId', $price->id)
                ->call('assignPlan')
                ->assertDispatched('toast', type: 'error', title: __("{$lang}.toasts.store_managed_subscription", ['provider' => PaymentProvider::AppStore->label()]));

            $this->assertSame('active', $store->refresh()->status->value);
            $this->assertSame(1, $owner->subscriptions()->count());
        }
    }
}
