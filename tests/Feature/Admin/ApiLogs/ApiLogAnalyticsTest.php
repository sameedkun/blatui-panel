<?php

namespace Tests\Feature\Admin\ApiLogs;

use App\Enum\ApiStatsPeriod;
use App\Livewire\Admin\Administration\ApiLogs\Analytics;
use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestStat;
use App\Models\User;
use App\Services\ApiLog\AggregationService;
use App\Support\ApiLogs\AnalyticsRange;
use App\Support\ApiLogs\ApiLogAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiLogAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-23 12:30:00'));
    }

    private function analytics(): ApiLogAnalytics
    {
        return app(ApiLogAnalytics::class);
    }

    private function actingAsAdminWith(array $permissions): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    private function log(string $at, int $status = 200, float $durationMs = 40, int $weight = 1, string $route = 'api/v1/me', string $method = 'GET'): ApiRequestLog
    {
        return ApiRequestLog::factory()
            ->route($method, $route)
            ->status($status)
            ->create(['created_at' => Date::parse($at), 'duration_ms' => $durationMs, 'sample_weight' => $weight]);
    }

    public function test_kpis_are_sample_weighted(): void
    {
        $this->log('2026-09-23 12:00:00', 200, 40, weight: 10);
        $this->log('2026-09-23 12:05:00', 404, 20);
        $this->log('2026-09-23 12:10:00', 500, 900);

        $kpis = $this->analytics()->kpis(AnalyticsRange::fromKey('1h'));

        $this->assertSame(12, $kpis['requests']);
        $this->assertSame(1, $kpis['errors_4xx']);
        $this->assertSame(1, $kpis['errors_5xx']);
        $this->assertSame(8.33, $kpis['rate_5xx']);
        $this->assertSame(50.0, $kpis['p50_ms']);
        $this->assertSame(1000.0, $kpis['p99_ms']);
        $this->assertSame(900.0, $kpis['max_ms']);
    }

    public function test_a_rollup_range_is_completed_by_the_raw_tail(): void
    {
        // Hour stats cover everything up to 11:00; the current hour exists only as raw rows.
        $this->log('2026-09-23 10:15:00');
        $this->log('2026-09-23 10:20:00', 500);
        app(AggregationService::class)->aggregate();
        $this->log('2026-09-23 12:10:00');

        // Hour 10 now exists both as a stat row and as raw rows — it must be counted once.
        $kpis = $this->analytics()->kpis(AnalyticsRange::fromKey('7d'));

        $this->assertSame(3, $kpis['requests']);
        $this->assertSame(1, $kpis['errors_5xx']);
        $this->assertSame([200 => 2, 500 => 1], $this->analytics()->statusCodes(AnalyticsRange::fromKey('7d')));
    }

    public function test_a_range_falls_back_to_raw_when_nothing_has_been_rolled_up(): void
    {
        $this->log('2026-09-20 10:15:00');

        $this->assertSame(1, $this->analytics()->kpis(AnalyticsRange::fromKey('30d'))['requests']);
    }

    public function test_requests_over_time_are_bucketed_by_status_class(): void
    {
        $this->log('2026-09-23 12:00:10');
        $this->log('2026-09-23 12:00:40', 422);
        $this->log('2026-09-23 12:29:00', 503);

        $chart = $this->analytics()->requestsOverTime(AnalyticsRange::fromKey('1h'));

        $this->assertCount(61, $chart['labels']);
        $this->assertSame(1, array_sum($chart['series'][2]));
        $this->assertSame(1, array_sum($chart['series'][4]));
        $this->assertSame(1, array_sum($chart['series'][5]));
        $this->assertSame(0, array_sum($chart['series'][3]));
    }

    public function test_endpoint_rankings(): void
    {
        $this->log('2026-09-23 12:00:00', 200, 2000, route: 'api/v1/tickets', method: 'POST');
        $this->log('2026-09-23 12:01:00', 500, 30, route: 'api/v1/tickets', method: 'POST');
        $this->log('2026-09-23 12:02:00', 200, 10);
        $this->log('2026-09-23 12:03:00', 200, 10);
        $this->log('2026-09-23 12:04:00', 200, 10);

        $range = AnalyticsRange::fromKey('1h');

        $this->assertSame('api/v1/tickets', $this->analytics()->slowestEndpoints($range)[0]['route_uri']);
        $this->assertSame('api/v1/me', $this->analytics()->busiestEndpoints($range)[0]['route_uri']);

        $erroring = $this->analytics()->erroringEndpoints($range);
        $this->assertCount(1, $erroring);
        $this->assertSame(['POST', 1, 50.0], [$erroring[0]['method'], $erroring[0]['errors_5xx'], $erroring[0]['error_rate']]);
    }

    public function test_raw_only_breakdowns_are_unavailable_past_raw_retention(): void
    {
        ApiRequestLog::factory()->create(['created_at' => now()->subMinutes(5), 'error_code' => 'DEVICE_BLOCKED', 'ip' => '203.0.113.5']);

        $this->assertSame(['DEVICE_BLOCKED' => 1], $this->analytics()->errorCodes(AnalyticsRange::fromKey('24h')));
        $this->assertSame(['203.0.113.5' => 1], $this->analytics()->topIps(AnalyticsRange::fromKey('7d')));
        $this->assertNull($this->analytics()->errorCodes(AnalyticsRange::fromKey('30d')));
        $this->assertNull($this->analytics()->kpis(AnalyticsRange::fromKey('30d'))['unique_users']);
    }

    public function test_top_exceptions_group_by_fingerprint(): void
    {
        $first = ApiRequestException::factory()->create(['created_at' => now()->subHour()]);
        ApiRequestException::factory()->create(['fingerprint' => $first->fingerprint, 'class' => $first->class, 'file' => $first->file, 'line' => $first->line, 'created_at' => now()->subMinutes(5)]);
        ApiRequestException::factory()->create(['created_at' => now()->subMinutes(2)]);

        $top = $this->analytics()->topExceptions(AnalyticsRange::fromKey('24h'));

        $this->assertSame($first->fingerprint, $top[0]['fingerprint']);
        $this->assertSame(2, $top[0]['occurrences']);
        $this->assertCount(2, $top);
    }

    public function test_all_time_starts_at_the_oldest_monthly_rollup(): void
    {
        ApiRequestStat::factory()->period(ApiStatsPeriod::Month, '2024-03-01 00:00:00')->create();

        $range = $this->analytics()->range('all');

        $this->assertSame('2024-03-01', $range->start->format('Y-m-d'));
        $this->assertSame('2024-03', array_key_first($range->buckets()));
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $this->assertSame(AnalyticsRange::DEFAULT, AnalyticsRange::fromKey('forever')->key);
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);

        $this->get(route('admin.api-logs.analytics'))->assertForbidden();
    }

    public function test_the_page_renders_every_range(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.analytics.view']);
        $this->log('2026-09-23 12:10:00', 500);
        $this->log('2026-09-23 09:10:00');
        app(AggregationService::class)->aggregate();

        foreach (AnalyticsRange::KEYS as $key) {
            Livewire::test(Analytics::class)
                ->call('selectRange', $key)
                ->assertOk()
                ->assertSet('range', $key)
                ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['requests'] >= 1);
        }
    }

    public function test_selecting_an_invalid_range_is_ignored(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.analytics.view']);

        Livewire::test(Analytics::class)
            ->call('selectRange', 'bogus')
            ->assertSet('range', AnalyticsRange::DEFAULT);
    }
}
