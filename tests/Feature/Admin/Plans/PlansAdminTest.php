<?php

namespace Tests\Feature\Admin\Plans;

use App\Livewire\Admin\Management\Plans\Form;
use App\Livewire\Admin\Management\Plans\Index;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlansAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_plan_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('plans', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('plans', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_plan_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create(['name' => 'Professional']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.plans.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('plans.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('plans.subtitle'));

        $createResponse = $this->withCookie('locale', 'tr')->get(route('admin.plans.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('<title>'.__('plans.page_titles.create').' — '.config('app.name').'</title>', false);

        $editResponse = $this->withCookie('locale', 'tr')->get(route('admin.plans.edit', $plan));
        $editResponse->assertOk();
        $editResponse->assertSee('<title>'.__('plans.page_titles.edit').' — '.config('app.name').'</title>', false);

        $showResponse = $this->withCookie('locale', 'tr')->get(route('admin.plans.show', $plan));
        $showResponse->assertOk();
        // e() to match Blade's own escaping — the raw-HTML comparison has to be escaped
        // exactly the way the rendered title is, or a name containing an apostrophe fails.
        $showResponse->assertSee(
            '<title>'.__('plans.title').' — '.e($plan->name).' — '.config('app.name').'</title>',
            false,
        );
    }

    public function test_plan_action_toast_uses_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create(['name' => 'Professional', 'is_active' => true]);

        Livewire::test(Index::class)
            ->call('toggleActive', $plan->id)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('plans.toasts.deactivated', ['name' => $plan->name]),
            );
    }

    public function test_creating_a_plan_persists_prices_and_providers(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Form::class)
            ->set('name', 'Pro')
            ->set('description', 'The pro plan')
            ->set('is_active', true)
            ->set('prices', [[
                'id' => null,
                'amount' => '9.99',
                'compare_at_amount' => '',
                'currency' => 'usd',
                'billing_period' => 1,
                'billing_interval' => 'month',
                'trial_period' => 7,
                'trial_interval' => 'day',
                'grace_period' => 0,
                'grace_interval' => 'day',
                'is_active' => true,
                'providers' => [[
                    'id' => null,
                    'provider' => 'stripe',
                    'external_id' => 'price_123',
                    'is_active' => true,
                ]],
            ]])
            ->call('save')
            ->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('name', 'Pro')->firstOrFail();
        $this->assertSame('pro', $plan->slug);

        $price = $plan->prices()->firstOrFail();
        $this->assertSame('9.99', (string) $price->amount);
        $this->assertSame('USD', $price->currency);

        $provider = $price->providers()->firstOrFail();
        $this->assertSame('price_123', $provider->external_id);
    }

    public function test_editing_a_plan_removes_a_price_with_no_subscriptions(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        $keptPrice = PlanPrice::factory()->for($plan)->create();
        $removedPrice = PlanPrice::factory()->for($plan)->create();

        Livewire::test(Form::class, ['plan' => $plan])
            ->set('prices', [[
                'id' => $keptPrice->id,
                'amount' => (string) $keptPrice->amount,
                'compare_at_amount' => '',
                'currency' => $keptPrice->currency,
                'billing_period' => $keptPrice->billing_period,
                'billing_interval' => $keptPrice->billing_interval->value,
                'trial_period' => 0,
                'trial_interval' => 'day',
                'grace_period' => 0,
                'grace_interval' => 'day',
                'is_active' => true,
                'providers' => [],
            ]])
            ->call('save')
            ->assertRedirect(route('admin.plans.index'));

        $this->assertModelExists($keptPrice);
        $this->assertModelMissing($removedPrice);
    }

    public function test_editing_a_plan_cannot_remove_a_price_with_subscriptions(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        Subscription::factory()->for($plan)->for($price, 'planPrice')->create();

        Livewire::test(Form::class, ['plan' => $plan])
            ->set('prices', [])
            ->call('save')
            ->assertNoRedirect();

        $this->assertModelExists($price);
    }

    public function test_deleting_a_plan_with_no_subscriptions_succeeds(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();

        Livewire::test(Index::class)
            ->call('confirmDelete', $plan->id)
            ->call('delete');

        $this->assertModelMissing($plan);
    }

    public function test_deleting_a_plan_with_subscriptions_is_blocked(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create();
        Subscription::factory()->for($plan)->create();

        Livewire::test(Index::class)->call('confirmDelete', $plan->id);

        $this->assertModelExists($plan);
    }

    public function test_bulk_delete_skips_plans_with_subscriptions(): void
    {
        $this->actingAsSuperAdmin();
        $deletable = Plan::factory()->create();
        $blocked = Plan::factory()->create();
        Subscription::factory()->for($blocked)->create();

        Livewire::test(Index::class)
            ->set('selectedIds', [$deletable->id, $blocked->id])
            ->call('executeBulkDelete');

        $this->assertModelMissing($deletable);
        $this->assertModelExists($blocked);
    }

    public function test_toggle_active_flips_the_plan_status(): void
    {
        $this->actingAsSuperAdmin();
        $plan = Plan::factory()->create(['is_active' => true]);

        Livewire::test(Index::class)->call('toggleActive', $plan->id);

        $this->assertFalse($plan->fresh()->is_active);
    }
}
