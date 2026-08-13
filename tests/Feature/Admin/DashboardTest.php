<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Dashboard\Analytics;
use App\Livewire\Admin\Dashboard\Infrastructure;
use App\Livewire\Admin\Dashboard\Overview;
use App\Livewire\Admin\Dashboard\Reports;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** A staff member holding only the named permissions, plus panel access. */
    private function actingAsStaffWith(array $permissions): User
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        foreach (['panel.access-admin', 'dashboard.view', ...$permissions] as $name) {
            $staff->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->actingAs($staff);

        return $staff;
    }

    /**
     * Render a tab's real view rather than its skeleton.
     *
     * Tab components carry #[Lazy], so a plain Livewire::test() only ever renders the
     * placeholder — every assertion about their data or markup has to disable that first.
     */
    private function renderTab(string $component, ?DateRange $range = null)
    {
        Livewire::withoutLazyLoading();

        return Livewire::test($component, [
            'selectedRange' => ($range ?? DateRange::Month)->value,
        ]);
    }

    // ── Access ────────────────────────────────────────────────────────────────

    public function test_the_dashboard_is_unreachable_without_authentication(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_app_user_cannot_reach_the_panel_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'app']))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_banned_staff_member_cannot_reach_the_dashboard(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => now()]);
        $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'panel.access-admin', 'guard_name' => 'web']));

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_super_admin_can_load_the_dashboard(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.dashboard'))->assertOk();
    }

    /**
     * Documents current behaviour rather than asserting an ideal.
     *
     * Unlike every other admin route group, the dashboard route carries no
     * `permission:dashboard.view` middleware, even though `config/panel.php` declares the
     * permission — panel access alone is enough to land on it. The page still degrades
     * safely because every card is individually gated.
     */
    public function test_panel_access_alone_reaches_the_dashboard(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'panel.access-admin', 'guard_name' => 'web']));

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
    }

    // ── Tabs ──────────────────────────────────────────────────────────────────

    public function test_the_shell_offers_every_tab_and_defaults_to_overview(): void
    {
        $this->actingAsSuperAdmin();

        $component = Livewire::test(Dashboard::class)->assertSet('tab', 'overview');

        $keys = collect($component->viewData('tabs'))->pluck('key')->all();

        $this->assertSame(['overview', 'analytics', 'reports', 'system', 'infrastructure'], $keys);
    }

    public function test_selecting_a_tab_switches_the_active_component(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->call('selectTab', 'reports')
            ->assertSet('tab', 'reports');
    }

    public function test_an_unknown_tab_in_the_url_falls_back_to_the_first_one(): void
    {
        $this->actingAsSuperAdmin();

        $active = Livewire::test(Dashboard::class)
            ->set('tab', 'not-a-tab')
            ->viewData('active');

        $this->assertSame('overview', $active['key']);
    }

    // ── Range control ─────────────────────────────────────────────────────────

    public function test_the_range_defaults_to_thirty_days_and_accepts_a_valid_change(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->assertSet('selectedRange', DateRange::Month->value)
            ->call('selectRange', DateRange::Year->value)
            ->assertSet('selectedRange', DateRange::Year->value);
    }

    public function test_an_unknown_range_falls_back_instead_of_erroring(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->call('selectRange', 'not-a-range')
            ->assertSet('selectedRange', DateRange::Month->value)
            ->assertOk();
    }

    public function test_refreshing_metrics_dispatches_a_toast(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->call('refreshMetrics')
            ->assertDispatched('toast');
    }

    // ── Tab rendering ─────────────────────────────────────────────────────────

    public function test_every_tab_renders_for_a_super_admin(): void
    {
        // Seed the real permission set: the tab views run @can checks, and Spatie needs
        // those permissions to exist the way they do in any real deployment.
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        foreach ([Overview::class, Analytics::class, Reports::class, Infrastructure::class] as $tab) {
            $this->renderTab($tab)->assertOk();
        }
    }

    public function test_every_tab_renders_across_every_range(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        foreach (DateRange::cases() as $range) {
            $this->renderTab(Overview::class, $range)->assertOk();
            $this->renderTab(Analytics::class, $range)->assertOk();
            $this->renderTab(Reports::class, $range)->assertOk();
        }
    }

    // ── Per-card permission gating ────────────────────────────────────────────

    public function test_analytics_omits_payloads_the_viewer_cannot_see(): void
    {
        $this->actingAsStaffWith(['tickets.view']);

        $component = $this->renderTab(Analytics::class);

        $this->assertNotNull($component->viewData('tickets'), 'Ticket access was granted.');

        foreach (['revenue', 'churn', 'devices', 'deviceTypes', 'platforms', 'countries', 'contexts'] as $key) {
            $this->assertNull(
                $component->viewData($key),
                "[{$key}] must not be queried for a staff member without its permission.",
            );
        }
    }

    public function test_reports_omits_payloads_the_viewer_cannot_see(): void
    {
        $this->actingAsStaffWith(['subscriptions.view']);

        $component = $this->renderTab(Reports::class);

        $this->assertNotNull($component->viewData('statuses'));
        $this->assertNotNull($component->viewData('subscriptions'));

        foreach (['priorities', 'workload', 'oldestTickets', 'blocks', 'risk', 'plans'] as $key) {
            $this->assertNull($component->viewData($key), "[{$key}] must not be queried without its permission.");
        }
    }

    public function test_overview_kpi_cards_are_filtered_by_permission(): void
    {
        $this->actingAsStaffWith(['tickets.view']);

        $labels = collect($this->renderTab(Overview::class)->viewData('cards'))
            ->pluck('label')
            ->all();

        $this->assertContains(__('dashboard.stats.open_tickets'), $labels);
        $this->assertNotContains(__('dashboard.stats.revenue'), $labels);
        $this->assertNotContains(__('dashboard.stats.total_users'), $labels);
    }

    // ── "View all" links ──────────────────────────────────────────────────────

    public function test_view_all_links_only_render_when_the_viewer_can_reach_the_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        $this->renderTab(Overview::class)->assertSee(route('admin.activity-logs.index'), false);

        $this->renderTab(Reports::class)
            ->assertSee(route('admin.tickets.index'), false)
            ->assertSee(route('admin.subscriptions.index'), false)
            ->assertSee(route('admin.blocked-ips.index'), false)
            ->assertSee(route('admin.devices.shared-fingerprints'), false);
    }

    public function test_a_view_all_link_is_hidden_when_its_destination_is_forbidden(): void
    {
        // Ticket data is visible, so the card renders — but nothing else is, so no other
        // card and no other link may appear.
        $this->actingAsStaffWith(['tickets.view']);

        $this->renderTab(Reports::class)
            ->assertSee(route('admin.tickets.index'), false)
            ->assertDontSee(route('admin.subscriptions.index'), false)
            ->assertDontSee(route('admin.blocked-ips.index'), false)
            ->assertDontSee(route('admin.devices.shared-fingerprints'), false);

        $this->renderTab(Overview::class)
            ->assertDontSee(route('admin.activity-logs.index'), false);
    }

    /**
     * Shared Fingerprints sits inside the devices route group, so reaching it needs
     * `devices.view` on top of `devices.investigate` — holding only the latter must not
     * surface a link that would 403.
     */
    public function test_the_shared_fingerprints_link_requires_both_device_permissions(): void
    {
        $this->actingAsStaffWith(['devices.investigate']);

        $this->renderTab(Reports::class)->assertDontSee(route('admin.devices.shared-fingerprints'), false);
    }

    // ── Localization ──────────────────────────────────────────────────────────

    public function test_english_and_turkish_dashboard_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('dashboard', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('dashboard', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }
}
