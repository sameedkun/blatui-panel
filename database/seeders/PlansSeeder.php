<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();
    }

    protected function seedPlans(): void
    {
        $starter = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Everything you need to get going.',
            'sort_order' => 1,
            'is_best_deal' => false,
            'features' => [
                'device_limit' => 1,
                'ad_free' => false,
            ],
        ]);

        PlanPrice::create([
            'plan_id' => $starter->id,
            'amount' => 4.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
        ]);

        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For power users who want it all.',
            'sort_order' => 2,
            'is_best_deal' => true,
            'features' => [
                'device_limit' => 5,
                'ad_free' => true,
            ],
        ]);

        PlanPrice::create([
            'plan_id' => $pro->id,
            'amount' => 14.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
            'trial_period' => 7,
            'trial_interval' => 'day',
        ]);

        PlanPrice::create([
            'plan_id' => $pro->id,
            'amount' => 149.99,
            'compare_at_amount' => 179.88,
            'billing_period' => 1,
            'billing_interval' => 'year',
        ]);

        $business = Plan::create([
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Built for teams.',
            'sort_order' => 3,
            'is_best_deal' => false,
            'features' => [
                'device_limit' => 20,
                'ad_free' => true,
            ],
        ]);

        PlanPrice::create([
            'plan_id' => $business->id,
            'amount' => 49.99,
            'billing_period' => 1,
            'billing_interval' => 'month',
        ]);
    }
}
