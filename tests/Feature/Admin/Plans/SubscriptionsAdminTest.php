<?php

namespace Tests\Feature\Admin\Plans;

use App\Enum\ReceiptType;
use App\Livewire\Admin\Management\Subscriptions\Index;
use App\Livewire\Admin\Management\Subscriptions\Show;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubscriptionsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    private function liveSubscription(?User $user = null): Subscription
    {
        $user ??= User::factory()->app()->create();
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 9.99]);

        return Subscription::factory()->for($user)->for($plan)->for($price, 'planPrice')->create([
            'status' => 'active',
            'ends_at' => now()->addMonth(),
            'is_recurring' => true,
        ]);
    }

    public function test_english_and_turkish_subscription_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('subscriptions', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('subscriptions', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_subscription_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsSuperAdmin();
        $subscription = $this->liveSubscription();

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.subscriptions.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('subscriptions.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('subscriptions.subtitle'));

        $showResponse = $this->withCookie('locale', 'tr')->get(route('admin.subscriptions.show', $subscription));
        $showResponse->assertOk();
        $showResponse->assertSee(
            '<title>'.__('subscriptions.title').' — '.$subscription->user->name.' — '.$subscription->plan->name.' — '.config('app.name').'</title>',
            false,
        );
        $showResponse->assertSee(__('subscriptions.overview.lifecycle_billing'));

        Livewire::test(Show::class, ['subscription' => $subscription])
            ->set('tab', 'receipts')
            ->assertSee(__('subscriptions.receipts.title'))
            ->set('tab', 'activity')
            ->assertSee(__('subscriptions.activity.title'));
    }

    public function test_subscription_action_toast_and_receipt_type_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $oldSubscription = $this->liveSubscription($user);
        $oldSubscription->update(['ends_at' => now()->addDays(10)]);

        $newSubscription = $this->liveSubscription($user);
        $newSubscription->update(['ends_at' => now()->addDays(40)]);

        Livewire::test(Show::class, ['subscription' => $oldSubscription])
            ->call('openCancelImmediatelyDialog', $oldSubscription->id)
            ->call('cancelImmediately')
            ->assertDispatched(
                'toast',
                type: 'error',
                title: __('subscriptions.toasts.no_longer_active'),
            );

        $this->assertSame(__('enums.receipt_type.Refund'), ReceiptType::Refund->label());
    }

    // ── Index ──────────────────────────────────────────────────────────────
    // Note: like Plans/Users/Guests, the Index page itself has no component-level
    // authorization check — subscriptions.view is enforced by route middleware only
    // (Livewire::test() bypasses HTTP routing, so there's nothing to assert here).

    public function test_index_lists_subscriptions_and_searches_by_user_and_plan(): void
    {
        $this->actingAsSuperAdmin();

        $user = User::factory()->app()->create(['name' => 'Ada Lovelace']);
        $sub = $this->liveSubscription($user);

        $other = $this->liveSubscription();

        Livewire::test(Index::class)
            ->assertSee('Ada Lovelace')
            ->assertSee($sub->plan->name)
            ->set('search', 'Ada Lovelace')
            ->assertSee('Ada Lovelace')
            ->assertDontSee($other->user->name);
    }

    public function test_index_filters_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = $this->liveSubscription();
        $cancelled = $this->liveSubscription();
        $cancelled->update(['status' => 'cancelled', 'ends_at' => now()->subDay(), 'is_recurring' => false]);

        Livewire::test(Index::class)
            ->set('filters.status', 'cancelled')
            ->assertSee($cancelled->user->name)
            ->assertDontSee($active->user->name);
    }

    public function test_index_row_action_requires_subscriptions_manage(): void
    {
        // Has view but not manage.
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        Permission::firstOrCreate(['name' => 'panel.access-admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'subscriptions.view', 'guard_name' => 'web']);
        $staff->givePermissionTo(['panel.access-admin', 'subscriptions.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff);

        $sub = $this->liveSubscription();

        Livewire::test(Index::class)
            ->call('openCancelImmediatelyDialog', $sub->id)
            ->assertForbidden();
    }

    // ── Show ───────────────────────────────────────────────────────────────

    public function test_show_403s_without_subscriptions_manage(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);
        $sub = $this->liveSubscription();

        Livewire::test(Show::class, ['subscription' => $sub])->assertForbidden();
    }

    public function test_show_page_renders_plan_user_and_stats(): void
    {
        $this->actingAsSuperAdmin();
        $sub = $this->liveSubscription();

        $component = Livewire::test(Show::class, ['subscription' => $sub]);

        $component->assertSee($sub->plan->name);
        $component->assertSee($sub->user->name);

        $stats = collect($component->instance()->statCards())->keyBy('label');
        $this->assertSame('Active', $stats['Status']['value']);
    }

    public function test_cancel_immediately_on_the_live_row_ends_access_now(): void
    {
        $this->actingAsSuperAdmin();
        $sub = $this->liveSubscription();

        Livewire::test(Show::class, ['subscription' => $sub])
            ->call('openCancelImmediatelyDialog', $sub->id)
            ->set('cancelReason', 'Refund requested')
            ->call('cancelImmediately');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status->value);
        $this->assertTrue($sub->ends_at->isPast() || $sub->ends_at->equalTo(now()) || $sub->ends_at->diffInSeconds(now()) < 5);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $sub->user_id,
            'event' => 'cancelled',
        ]);
    }

    public function test_cancel_at_period_end_keeps_access_until_ends_at(): void
    {
        $this->actingAsSuperAdmin();
        $sub = $this->liveSubscription();
        $originalEndsAt = $sub->ends_at;

        Livewire::test(Show::class, ['subscription' => $sub])
            ->call('openCancelAtPeriodEndDialog', $sub->id)
            ->set('cancelReason', '')
            ->call('cancelAtPeriodEnd');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status->value);
        $this->assertFalse($sub->is_recurring);
        $this->assertTrue($sub->ends_at->equalTo($originalEndsAt));
    }

    public function test_reactivate_row_restores_a_cancelled_but_still_live_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $sub = $this->liveSubscription($user);
        $sub->update([
            'status' => 'cancelled',
            'ends_at' => now()->addWeek(),
            'is_recurring' => false,
            'cancelled_by' => 'admin',
            'cancelled_reason' => 'Requested',
        ]);

        Livewire::test(Show::class, ['subscription' => $sub])->call('reactivateRow', $sub->id);

        $sub->refresh();
        $this->assertSame('active', $sub->status->value);
        $this->assertFalse($sub->is_recurring);
        $this->assertNull($sub->cancelled_by);
    }

    public function test_cancel_is_rejected_once_the_row_is_no_longer_the_users_live_subscription(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $old = $this->liveSubscription($user);
        $old->update(['ends_at' => now()->addDays(10)]);

        // A new subscription (with a further-out ends_at) replaces it as the
        // user's "live" one — activeSubscription() picks the latest ends_at.
        $new = $this->liveSubscription($user);
        $new->update(['ends_at' => now()->addDays(40)]);

        Livewire::test(Show::class, ['subscription' => $old])
            ->call('openCancelImmediatelyDialog', $old->id)
            ->call('cancelImmediately')
            ->assertDispatched('toast', type: 'error');

        // The old row is untouched — the guard rejected the action outright.
        $this->assertSame('active', $old->fresh()->status->value);
    }

    public function test_activity_tab_shows_the_users_subscription_events(): void
    {
        $this->actingAsSuperAdmin();
        $sub = $this->liveSubscription();

        Livewire::test(Show::class, ['subscription' => $sub])
            ->call('openCancelImmediatelyDialog', $sub->id)
            ->set('cancelReason', 'Testing')
            ->call('cancelImmediately');

        $component = Livewire::test(Show::class, ['subscription' => $sub])->set('tab', 'activity');
        $component->assertSee('Subscription Cancelled');
    }
}
