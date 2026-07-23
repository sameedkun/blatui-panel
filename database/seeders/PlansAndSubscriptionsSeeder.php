<?php

namespace Database\Seeders;

use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Local-only demo data for the Plans admin UI: a handful of realistic plans
 * (with prices and payment-provider mappings) and subscriptions covering
 * every {@see SubscriptionStatus} case, so the Plans index/show
 * pages have something real to look at. Not idempotent — run once per fresh
 * migrate, same as UserSeeder's local-only block and the 50 random users in
 * DatabaseSeeder.
 */
class PlansAndSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $p = $this->seedPlans();
        $this->seedSubscriptions($p);
    }

    /**
     * DatabaseSeeder uses Laravel's WithoutModelEvents trait, which mutes
     * model events (including Sluggable's creation hook) for every nested
     * seeder called through it — so `slug` never gets generated when run via
     * `db:seed`/`migrate:fresh --seed`. Set it explicitly here rather than
     * relying on that event, so this seeder works the same standalone or not.
     */
    protected function createPlan(array $attributes): Plan
    {
        $plan = Plan::factory()->make($attributes);
        $plan->forceFill(['slug' => Str::slug($plan->name)]);
        $plan->save();

        return $plan;
    }

    /**
     * @return Collection<string, Plan|PlanPrice>
     */
    protected function seedPlans(): Collection
    {
        $starter = $this->createPlan([
            'name' => 'Starter',
            'description' => 'Everything you need to get going.',
            'sort_order' => 1,
            'is_best_deal' => false,
            'features' => ['device_limit' => 1, 'ad_free' => false],
        ]);
        $starterMonthly = PlanPrice::factory()->for($starter)->create([
            'amount' => 4.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
        ]);
        PlanPriceProvider::factory()->for($starterMonthly, 'planPrice')->create([
            'provider' => 'stripe',
            'external_id' => 'price_starter_month',
        ]);

        $pro = $this->createPlan([
            'name' => 'Pro',
            'description' => 'For power users who want it all.',
            'sort_order' => 2,
            'is_best_deal' => true,
            'features' => ['device_limit' => 5, 'ad_free' => true],
        ]);
        $proMonthly = PlanPrice::factory()->for($pro)->create([
            'amount' => 14.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
            'trial_period' => 7,
            'trial_interval' => 'day',
        ]);
        $proYearly = PlanPrice::factory()->for($pro)->yearly()->create([
            'amount' => 149.99,
            'compare_at_amount' => 179.88,
        ]);
        PlanPriceProvider::factory()->for($proMonthly, 'planPrice')->create([
            'provider' => 'stripe',
            'external_id' => 'price_pro_month',
        ]);
        PlanPriceProvider::factory()->for($proYearly, 'planPrice')->create([
            'provider' => 'stripe',
            'external_id' => 'price_pro_year',
        ]);
        PlanPriceProvider::factory()->for($proYearly, 'planPrice')->create([
            'provider' => 'oxapay',
            'external_id' => 'pro-year-oxapay',
        ]);

        $business = $this->createPlan([
            'name' => 'Business',
            'description' => 'Built for teams.',
            'sort_order' => 3,
            'features' => ['device_limit' => 20, 'ad_free' => true],
        ]);
        $businessMonthly = PlanPrice::factory()->for($business)->create([
            'amount' => 49.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
        ]);
        PlanPriceProvider::factory()->for($businessMonthly, 'planPrice')->create([
            'provider' => 'appstore',
            'external_id' => 'com.app.business.monthly',
        ]);

        $legacy = $this->createPlan([
            'name' => 'Legacy',
            'description' => 'Retired plan, kept for existing subscribers only.',
            'sort_order' => 4,
            'is_active' => false,
        ]);
        $legacyMonthly = PlanPrice::factory()->for($legacy)->create([
            'amount' => 2.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
            'is_active' => false,
        ]);

        return collect(compact(
            'starter', 'starterMonthly',
            'pro', 'proMonthly', 'proYearly',
            'business', 'businessMonthly',
            'legacy', 'legacyMonthly',
        ));
    }

    /**
     * @param  Collection<string, Plan|PlanPrice>  $p
     */
    protected function seedSubscriptions(Collection $p): void
    {
        // Active.
        Subscription::factory()
            ->for(User::factory()->app())
            ->for($p['pro'])->for($p['proMonthly'], 'planPrice')
            ->create(['status' => 'active', 'amount_paid' => 14.99, 'provider' => 'stripe']);

        Subscription::factory()
            ->for(User::factory()->app())
            ->for($p['business'])->for($p['businessMonthly'], 'planPrice')
            ->create(['status' => 'active', 'amount_paid' => 49.99, 'provider' => 'appstore']);

        // Trialing.
        Subscription::factory()->trialing()
            ->for(User::factory()->app())
            ->for($p['pro'])->for($p['proMonthly'], 'planPrice')
            ->create(['amount_paid' => 0, 'provider' => 'stripe']);

        // Grace period (payment overdue, access not cut off yet).
        Subscription::factory()->grace()
            ->for(User::factory()->app())
            ->for($p['starter'])->for($p['starterMonthly'], 'planPrice')
            ->create(['amount_paid' => 4.99, 'provider' => 'stripe']);

        // Expired (on the retired Legacy plan).
        Subscription::factory()->expired()
            ->for(User::factory()->app())
            ->for($p['legacy'])->for($p['legacyMonthly'], 'planPrice')
            ->create(['amount_paid' => 2.99, 'provider' => 'local']);

        // Cancelled by the user.
        Subscription::factory()->cancelled('user')
            ->for(User::factory()->app())
            ->for($p['starter'])->for($p['starterMonthly'], 'planPrice')
            ->create(['amount_paid' => 4.99, 'provider' => 'stripe']);

        // Cancelled by an admin.
        Subscription::factory()->cancelled('system')
            ->for(User::factory()->app())
            ->for($p['business'])->for($p['businessMonthly'], 'planPrice')
            ->create(['amount_paid' => 49.99, 'provider' => 'appstore']);

        // Failed renewal payment.
        Subscription::factory()->failed()
            ->for(User::factory()->app())
            ->for($p['pro'])->for($p['proYearly'], 'planPrice')
            ->create(['amount_paid' => 149.99, 'provider' => 'stripe']);

        // A renewal chain: an expired monthly subscription upgraded into an
        // active yearly one, linked via previous_subscription_id — exercises
        // Subscription::previousSubscription().
        $renewalUser = User::factory()->app()->create();
        $previous = Subscription::factory()->expired()
            ->for($renewalUser)
            ->for($p['pro'])->for($p['proMonthly'], 'planPrice')
            ->create(['amount_paid' => 14.99, 'provider' => 'stripe']);

        Subscription::factory()
            ->for($renewalUser)
            ->for($p['pro'])->for($p['proYearly'], 'planPrice')
            ->create([
                'status' => 'active',
                'amount_paid' => 149.99,
                'provider' => 'stripe',
                'previous_subscription_id' => $previous->id,
                'proration_meta' => ['credit' => 4.20, 'from_plan' => $p['pro']->slug, 'new_amount' => 149.99],
            ]);
    }
}
