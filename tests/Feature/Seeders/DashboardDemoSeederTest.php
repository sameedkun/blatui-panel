<?php

namespace Tests\Feature\Seeders;

use App\Enum\ReportStatus;
use App\Livewire\Admin\Dashboard\Analytics;
use App\Livewire\Admin\Dashboard\Index;
use App\Models\ApiLog\ApiRequestStat;
use App\Models\Report\GeneratedReport;
use App\Models\Report\ScheduledReport;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\Metric;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_it_fills_every_dashboard_data_source(): void
    {
        $this->seed(DashboardDemoSeeder::class);

        $this->assertGreaterThan(300, User::query()->appUsers()->where('email', 'like', '%@'.DashboardDemoSeeder::EMAIL_DOMAIN)->count());
        $this->assertGreaterThan(0, User::query()->guests()->count());
        $this->assertGreaterThan(100, Subscription::query()->count());
        $this->assertSame(170, Ticket::query()->count());
        $this->assertGreaterThan(1000, Activity::query()->where('event', 'login')->count());
        $this->assertGreaterThan(0, ApiRequestStat::query()->count(), 'API traffic is rolled up like the scheduler would.');
        $this->assertSame(2, ScheduledReport::query()->count());
        $this->assertSame(2, GeneratedReport::query()->where('status', ReportStatus::Completed)->count(), 'Reports are generated (sync queue in tests).');
        $this->assertSame(1, GeneratedReport::query()->where('status', ReportStatus::Failed)->count());
    }

    public function test_it_is_deterministic_and_does_not_run_twice(): void
    {
        $this->seed(DashboardDemoSeeder::class);
        $users = User::query()->count();

        $this->seed(DashboardDemoSeeder::class);

        $this->assertSame($users, User::query()->count());
    }

    public function test_the_seeded_dashboard_shows_real_numbers(): void
    {
        $this->seed(DashboardDemoSeeder::class);

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        $overview = Livewire::test(Index::class)->assertOk()->viewData('overview');
        $kpis = collect($overview['kpis'])->mapWithKeys(fn (Metric $metric): array => [$metric->label => $metric->value]);

        $this->assertGreaterThan(0, $kpis[__('dashboard.kpis.total_users')]);
        $this->assertGreaterThan(0, $kpis[__('dashboard.kpis.revenue')]);
        $this->assertGreaterThan(0, $kpis[__('dashboard.kpis.mrr')]);

        foreach ($overview['trends'] as $chart) {
            $this->assertInstanceOf(Chart::class, $chart);
            $this->assertFalse($chart->isEmpty(), "{$chart->title} should have data.");
        }

        foreach (['audience', 'revenue', 'subscriptions', 'support', 'security', 'api'] as $tab) {
            $rows = Livewire::withQueryParams(['tab' => $tab])->test(Analytics::class)->assertOk()->viewData('rows');
            $charts = collect($rows)->flatMap(fn ($row) => $row->blocks)->whereInstanceOf(Chart::class);

            $this->assertTrue($charts->contains(fn (Chart $chart): bool => ! $chart->isEmpty()), "The {$tab} tab should chart real data.");
        }
    }
}
