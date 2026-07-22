<?php

namespace Tests\Feature;

use App\Enum\BillingInterval;
use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSubscriptionModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_relationships_and_casts(): void
    {
        $plan = Plan::factory()->create(['features' => ['a', 'b']]);
        $price = PlanPrice::factory()->for($plan)->create([
            'amount' => 9.99,
            'billing_interval' => 'year',
        ]);
        $provider = PlanPriceProvider::factory()->for($price, 'planPrice')->create([
            'provider' => 'stripe',
        ]);

        $this->assertTrue($plan->prices->contains($price));
        $this->assertTrue($price->providers->contains($provider));
        $this->assertSame($plan->id, $price->plan->id);
        $this->assertSame($price->id, $provider->planPrice->id);
        $this->assertIsArray($plan->features);
        $this->assertSame('9.99', (string) $price->amount);
        $this->assertSame(BillingInterval::Year, $price->billing_interval);
        $this->assertSame(PaymentProvider::Stripe, $provider->provider);
    }

    public function test_user_and_subscription_relationships_and_casts(): void
    {
        $user = User::factory()->app()->create();
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();

        $subscription = Subscription::factory()
            ->for($user)
            ->for($plan)
            ->for($price, 'planPrice')
            ->create();

        $receipt = SubscriptionReceipt::factory()->for($subscription)->create([
            'type' => 'renewal',
        ]);

        $this->assertTrue($user->subscriptions->contains($subscription));
        $this->assertSame($plan->id, $subscription->plan->id);
        $this->assertSame($price->id, $subscription->planPrice->id);
        $this->assertTrue($subscription->receipts->contains($receipt));
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(ReceiptType::Renewal, $receipt->type);
    }

    public function test_previous_subscription_self_reference(): void
    {
        $original = Subscription::factory()->create();
        $renewal = Subscription::factory()->create([
            'previous_subscription_id' => $original->id,
        ]);

        $this->assertSame($original->id, $renewal->previousSubscription->id);
    }

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
