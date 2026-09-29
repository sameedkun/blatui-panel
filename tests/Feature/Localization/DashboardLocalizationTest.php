<?php

namespace Tests\Feature\Localization;

use App\Livewire\Admin\Dashboard\Reports;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_and_turkish_dashboard_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('dashboard', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('dashboard', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_every_report_enum_label_is_translated_in_both_locales(): void
    {
        foreach (['report_format', 'report_status', 'report_frequency', 'report_source'] as $group) {
            $this->assertSame(
                array_keys(Lang::get("enums.{$group}", [], 'en')),
                array_keys(Lang::get("enums.{$group}", [], 'tr')),
                "enums.{$group} differs between locales.",
            );
        }
    }

    public function test_every_report_preset_and_source_has_a_label(): void
    {
        foreach (Reports::PRESETS as $preset) {
            $this->assertTrue(Lang::has("dashboard.reports.presets.{$preset}", 'en'), $preset);
        }

        foreach (Reports::SOURCES as $source) {
            $this->assertTrue(Lang::has("dashboard.reports.sources.{$source}", 'en'), $source);
        }
    }

    public function test_the_dashboard_pages_render_in_turkish(): void
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        foreach (['admin.dashboard', 'admin.dashboard.analytics', 'admin.dashboard.reports'] as $route) {
            $this->withCookie('locale', 'tr')->get(route($route))->assertOk();
        }
    }
}
