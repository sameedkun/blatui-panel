<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_plans(): void
    {
        Plan::factory()->create(['name' => 'Pro', 'is_active' => true]);
        Plan::factory()->create(['name' => 'Retired', 'is_active' => false]);

        $response = $this->getJson('/api/v1/plans')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Pro'));
        $this->assertFalse($names->contains('Retired'));
    }

    public function test_index_only_includes_active_prices_for_a_plan(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);
        $activePrice = PlanPrice::factory()->for($plan)->create(['is_active' => true, 'amount' => 9.99]);
        PlanPrice::factory()->for($plan)->create(['is_active' => false, 'amount' => 4.99]);

        $response = $this->getJson('/api/v1/plans')->assertOk();

        $prices = collect($response->json('data.0.prices'));
        $this->assertCount(1, $prices);
        $this->assertSame($activePrice->id, $prices->first()['id']);
    }

    public function test_index_only_includes_active_providers_for_a_price(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);
        $price = PlanPrice::factory()->for($plan)->create(['is_active' => true]);
        PlanPriceProvider::factory()->for($price)->create([
            'provider' => 'stripe',
            'external_id' => 'price_live_123',
            'is_active' => true,
        ]);
        PlanPriceProvider::factory()->for($price)->create([
            'provider' => 'appstore',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/plans')->assertOk();

        $providers = collect($response->json('data.0.prices.0.providers'));
        $this->assertCount(1, $providers);
        $this->assertSame('stripe', $providers->first()['provider']);
        $this->assertSame('price_live_123', $providers->first()['external_id']);
    }
}
