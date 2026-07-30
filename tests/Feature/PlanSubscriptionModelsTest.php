<?php

namespace Tests\Feature;

use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSubscriptionModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_active_reflects_status_and_end_date(): void
    {
        $active = Subscription::factory()->create(['status' => 'active', 'ends_at' => now()->addMonth()]);
        $trialing = Subscription::factory()->trialing()->create(['ends_at' => now()->addMonth()]);
        $expired = Subscription::factory()->expired()->create();
        $cancelled = Subscription::factory()->cancelled()->create();

        $this->assertTrue($active->isActive());
        $this->assertTrue($trialing->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($cancelled->isActive());
    }

    public function test_deleting_a_plan_cascades_to_prices_and_providers(): void
    {
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        $provider = PlanPriceProvider::factory()->for($price, 'planPrice')->create();

        $plan->forceDelete();

        $this->assertDatabaseMissing('plan_prices', ['id' => $price->id]);
        $this->assertDatabaseMissing('plan_price_providers', ['id' => $provider->id]);
    }

    public function test_deleting_a_subscription_cascades_to_receipts(): void
    {
        $subscription = Subscription::factory()->create();
        $receipt = SubscriptionReceipt::factory()->for($subscription)->create();

        $subscription->delete();

        $this->assertDatabaseMissing('subscription_receipts', ['id' => $receipt->id]);
    }
}
