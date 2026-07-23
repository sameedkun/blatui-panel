<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Plans\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlansShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_page_403s_without_plans_manage(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);
        $plan = Plan::factory()->create();

        Livewire::test(Show::class, ['plan' => $plan])->assertForbidden();
    }

    public function test_show_page_reports_subscription_stats(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10]);

        Subscription::factory()->for($plan)->for($price, 'planPrice')->create(['status' => 'active', 'amount_paid' => 10]);
        Subscription::factory()->for($plan)->for($price, 'planPrice')->create(['status' => 'trialing', 'amount_paid' => 0]);
        Subscription::factory()->for($plan)->for($price, 'planPrice')->create(['status' => 'cancelled', 'amount_paid' => 10]);

        $component = Livewire::test(Show::class, ['plan' => $plan]);

        $stats = $component->instance()->statCards();
        $this->assertSame('3', $stats[0]['value']);
        $this->assertSame('2', $stats[1]['value']);
        $this->assertSame('1', $stats[2]['value']);
        $this->assertSame('20.00', $stats[3]['value']);
        $this->assertSame('1', $stats[4]['value']);
    }

    public function test_subscriptions_tab_filters_by_status(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        $activeUser = User::factory()->app()->create(['email' => 'active-subscriber@example.com']);
        $cancelledUser = User::factory()->app()->create(['email' => 'cancelled-subscriber@example.com']);
        Subscription::factory()->for($plan)->for($activeUser)->create(['status' => 'active']);
        Subscription::factory()->for($plan)->for($cancelledUser)->create(['status' => 'cancelled']);

        Livewire::test(Show::class, ['plan' => $plan])
            ->set('tab', 'subscriptions')
            ->set('subsStatus', 'active')
            ->assertSee('active-subscriber@example.com')
            ->assertDontSee('cancelled-subscriber@example.com');
    }

    public function test_deleting_from_show_redirects_to_index_when_no_subscriptions(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();

        Livewire::test(Show::class, ['plan' => $plan])
            ->call('confirmDelete', $plan->id)
            ->call('delete')
            ->assertRedirect(route('admin.plans.index'));

        $this->assertModelMissing($plan);
    }

    public function test_deleting_from_show_is_blocked_when_subscriptions_exist(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        Subscription::factory()->for($plan)->create();

        Livewire::test(Show::class, ['plan' => $plan])->call('confirmDelete', $plan->id);

        $this->assertModelExists($plan);
    }

    public function test_toggle_active_from_show_page(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create(['is_active' => true]);

        Livewire::test(Show::class, ['plan' => $plan])->call('toggleActive', $plan->id);

        $this->assertFalse($plan->fresh()->is_active);
    }
}
