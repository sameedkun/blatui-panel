<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard\Analytics;
use App\Livewire\Admin\Dashboard\Overview;
use App\Livewire\Admin\Dashboard\Reports;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards the reason the dashboard is split into tabs at all.
 *
 * The whole point of the tabbed layout is that opening the dashboard runs one tab's worth
 * of queries instead of every chart on the page. Without a budget here, a future widget
 * added to a tab could quietly undo that, and nobody would notice until the page felt slow.
 *
 * These ceilings are deliberately loose — they exist to catch an order-of-magnitude
 * regression (an N+1 in a table, a metric resolved twice), not to pin an exact number.
 */
class DashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);
    }

    /** Queries run while rendering a tab, excluding auth/permission lookups. */
    private function countQueriesFor(string $component): int
    {
        // withoutLazyLoading() applies to the next test() call only — without repeating it
        // the second render returns the placeholder and counts zero queries, which looks
        // like a pass but measures nothing.
        $render = function () use ($component): void {
            Livewire::withoutLazyLoading();
            Livewire::test($component, ['selectedRange' => DateRange::Month->value]);
        };

        // Warm the auth + permission caches first so their queries don't land in the count.
        $render();

        $this->flushCache();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $render();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** The metric cache would otherwise make a second render free and the count meaningless. */
    private function flushCache(): void
    {
        cache()->clear();
    }

    public function test_the_overview_tab_stays_within_its_query_budget(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        // Measured at 24.
        $this->assertLessThanOrEqual(
            30,
            $count = $this->countQueriesFor(Overview::class),
            "Overview ran {$count} queries — past its budget. Check for a metric resolved twice.",
        );
    }

    public function test_the_analytics_tab_stays_within_its_query_budget(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        // Measured at 15.
        $this->assertLessThanOrEqual(
            20,
            $count = $this->countQueriesFor(Analytics::class),
            "Analytics ran {$count} queries — past its budget.",
        );
    }

    public function test_the_reports_tab_stays_within_its_query_budget(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAsSuperAdmin();

        // Measured at 19.
        $this->assertLessThanOrEqual(
            25,
            $count = $this->countQueriesFor(Reports::class),
            "Reports ran {$count} queries — past its budget. Check the row listings for an N+1.",
        );
    }

    public function test_a_viewer_without_permissions_runs_almost_no_metric_queries(): void
    {
        // Nothing but panel access: every gated payload should be skipped before it is
        // queried, not queried and then hidden.
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);

        // Measured at 0 — the gating skips the query, it does not run it and hide the result.
        $this->assertLessThanOrEqual(
            2,
            $count = $this->countQueriesFor(Analytics::class),
            "Analytics ran {$count} queries for a viewer allowed to see none of it.",
        );
    }
}
