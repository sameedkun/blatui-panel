<?php

namespace Tests\Feature\Admin\Accounts\Guests;

use App\Livewire\Admin\Management\Guests\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function activePrice(): PlanPrice
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        return PlanPrice::factory()->for($plan)->create(['is_active' => true, 'amount' => 9.99]);
    }

    public function test_subscription_actions_require_guests_manage_permission(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'staff', 'banned_at' => null]));

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
