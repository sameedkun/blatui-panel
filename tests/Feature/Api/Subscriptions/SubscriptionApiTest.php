<?php

namespace Tests\Feature\Api\Subscriptions;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function authenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->app()->create($attributes);
        $token = $user->createToken('device');

        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-a'), $token->accessToken, '127.0.0.1');

        return [$user, $token->plainTextToken];
    }

    public function test_current_subscription_returns_null_when_the_user_has_none(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJson(['data' => ['subscription' => null]]);
    }

    public function test_current_subscription_returns_the_active_subscription(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $plan = Plan::factory()->create(['name' => 'Pro']);
        $price = PlanPrice::factory()->for($plan)->create();

        $subscription = Subscription::factory()->for($user)->for($plan)->for($price, 'planPrice')->create([
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.id', $subscription->id)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.is_active', true)
            ->assertJsonPath('data.subscription.plan.name', 'Pro');
    }

    public function test_current_subscription_ignores_an_expired_subscription(): void
    {
        [$user, $token] = $this->authenticatedUser();
        Subscription::factory()->for($user)->expired()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJson(['data' => ['subscription' => null]]);
    }

    public function test_history_lists_every_subscription_the_user_has_had_most_recent_first(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $older = Subscription::factory()->for($user)->expired()->create(['starts_at' => now()->subMonths(2)]);
        $newer = Subscription::factory()->for($user)->create(['starts_at' => now()->subDays(1)]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription/history')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.0.id', $newer->id)
            ->assertJsonPath('data.subscriptions.1.id', $older->id)
            ->assertJsonCount(2, 'data.subscriptions');
    }

    public function test_history_never_returns_another_users_subscriptions(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $other = User::factory()->app()->create();
        Subscription::factory()->for($other)->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription/history')
            ->assertOk()
            ->assertJsonCount(0, 'data.subscriptions');
    }
}
