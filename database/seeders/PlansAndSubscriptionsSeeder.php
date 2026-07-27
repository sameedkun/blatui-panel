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
        $this->seedPlans();
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

        return collect(compact(
            'starter', 'starterMonthly',
            'pro', 'proMonthly', 'proYearly',
            'business', 'businessMonthly',
        ));
    }
}
