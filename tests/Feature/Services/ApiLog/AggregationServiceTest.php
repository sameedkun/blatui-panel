<?php

namespace Tests\Feature\Services\ApiLog;

use App\Enum\ApiStatsPeriod;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestStat;
use App\Services\ApiLog\AggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class AggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-23 12:30:00'));
    }

    private function service(): AggregationService
    {
        return app(AggregationService::class);
    }

    private function log(string $at, float $durationMs, int $status = 200, int $weight = 1, string $route = 'api/v1/me'): ApiRequestLog
    {
        return ApiRequestLog::factory()
            ->route('GET', $route)
            ->status($status)
            ->create(['created_at' => Date::parse($at), 'duration_ms' => $durationMs, 'sample_weight' => $weight, 'request_size' => 10, 'response_size' => 100]);
    }

    private function stat(ApiStatsPeriod $period, string $bucket, int $status = 200): ApiRequestStat
    {
        return ApiRequestStat::query()
            ->forPeriod($period)
            ->where('bucket', Date::parse($bucket)->format('Y-m-d H:i:s'))
            ->where('status_code', $status)
            ->firstOrFail();
    }

    public function test_complete_hours_are_rolled_up_from_raw_logs_with_sample_weights(): void
    {
        $this->log('2026-09-23 10:15:00', 8);
        $this->log('2026-09-23 10:40:00', 40);
        $this->log('2026-09-23 10:45:00', 300, weight: 10);
        $this->log('2026-09-23 10:50:00', 12000, status: 500);

        $this->service()->aggregate();

        $ok = $this->stat(ApiStatsPeriod::Hour, '2026-09-23 10:00:00');
        $this->assertSame(12, $ok->requests);
        $this->assertSame(2, $ok->status_class);
        $this->assertSame('v1', $ok->api_version);
        $this->assertEqualsWithDelta(3048.0, $ok->duration_sum_ms, 0.01);
        $this->assertEqualsWithDelta(8.0, $ok->duration_min_ms, 0.01);
        $this->assertEqualsWithDelta(300.0, $ok->duration_max_ms, 0.01);
        $this->assertSame(120, (int) $ok->request_bytes);
        $this->assertSame(1200, (int) $ok->response_bytes);
        $this->assertSame([1, 0, 1, 0, 0, 10, 0, 0, 0, 0, 0], array_map(fn (string $column): int => (int) $ok->{$column}, ApiRequestStat::histogramColumns()));

        $failed = $this->stat(ApiStatsPeriod::Hour, '2026-09-23 10:00:00', 500);
        $this->assertSame(1, $failed->requests);
        $this->assertSame(5, $failed->status_class);
        $this->assertSame(1, (int) $failed->h_inf);
    }

    public function test_the_current_incomplete_hour_is_not_aggregated(): void
    {
        $this->log('2026-09-23 12:05:00', 20);

        $this->service()->aggregate();

        $this->assertSame(0, ApiRequestStat::count());
    }

    public function test_aggregation_is_idempotent(): void
    {
        $this->log('2026-09-23 10:15:00', 20);
        $this->log('2026-09-23 11:15:00', 20);

        $this->service()->aggregate();
        $first = ApiRequestStat::orderBy('id')->get(['period', 'bucket', 'requests', 'duration_sum_ms'])->toArray();

        $this->service()->aggregate();
        $second = ApiRequestStat::orderBy('id')->get(['period', 'bucket', 'requests', 'duration_sum_ms'])->toArray();

        $this->assertSame($first, $second);
        $this->assertSame(2, ApiRequestStat::forPeriod(ApiStatsPeriod::Hour)->count());
    }

    public function test_a_late_flushed_request_is_picked_up_on_the_next_run(): void
    {
        $this->log('2026-09-23 11:10:00', 20);
        $this->service()->aggregate();

        $this->log('2026-09-23 11:55:00', 20);
        $this->travelTo(Date::parse('2026-09-23 13:05:00'));
        $this->service()->aggregate();

        $this->assertSame(2, $this->stat(ApiStatsPeriod::Hour, '2026-09-23 11:00:00')->requests);
    }

    public function test_a_first_run_catches_up_from_the_oldest_raw_log(): void
    {
        $this->log('2026-09-18 03:10:00', 20);
        $this->log('2026-09-22 22:10:00', 20);

        $rebuilt = $this->service()->aggregate();

        $this->assertSame(2, $rebuilt['hour']);
        $this->assertSame(2, $rebuilt['day']);
        $this->assertNotNull($this->stat(ApiStatsPeriod::Hour, '2026-09-18 03:00:00'));
        $this->assertNotNull($this->stat(ApiStatsPeriod::Day, '2026-09-18 00:00:00'));
    }

    public function test_hours_roll_into_days_and_days_into_months(): void
    {
        $this->log('2026-09-23 10:15:00', 20);
        $this->log('2026-09-23 18:15:00', 20);
        $this->log('2026-09-24 09:15:00', 20, status: 404);

        $this->travelTo(Date::parse('2026-09-25 00:30:00'));
        $this->service()->aggregate();

        $this->assertSame(2, $this->stat(ApiStatsPeriod::Day, '2026-09-23 00:00:00')->requests);
        $this->assertSame(1, $this->stat(ApiStatsPeriod::Day, '2026-09-24 00:00:00', 404)->requests);
        $this->assertSame(0, ApiRequestStat::forPeriod(ApiStatsPeriod::Month)->count());

        $this->travelTo(Date::parse('2026-10-01 01:00:00'));
        $this->service()->aggregate();

        $this->assertSame(2, $this->stat(ApiStatsPeriod::Month, '2026-09-01 00:00:00')->requests);
        $this->assertSame(1, $this->stat(ApiStatsPeriod::Month, '2026-09-01 00:00:00', 404)->requests);
    }

    public function test_a_bucket_whose_source_has_been_pruned_is_left_untouched(): void
    {
        ApiRequestStat::factory()->period(ApiStatsPeriod::Hour, '2026-09-23 11:00:00')->create(['requests' => 42]);

        $this->service()->aggregate();

        $this->assertSame(42, $this->stat(ApiStatsPeriod::Hour, '2026-09-23 11:00:00')->requests);
    }

    public function test_the_backfill_command_rebuilds_a_range(): void
    {
        $this->log('2026-09-20 10:15:00', 20);
        $this->log('2026-09-21 10:15:00', 20);

        $this->artisan('api-logs:aggregate', ['--from' => '2026-09-20', '--to' => '2026-09-20 23:59:59'])
            ->expectsOutputToContain('Rebuilt 1 hourly, 1 daily and 0 monthly bucket(s).')
            ->assertSuccessful();

        $this->assertSame(1, ApiRequestStat::forPeriod(ApiStatsPeriod::Hour)->count());
    }

    public function test_percentiles_are_read_off_the_histogram(): void
    {
        $histogram = ['h_le_10' => 50, 'h_le_100' => 45, 'h_le_1000' => 4, 'h_inf' => 1];

        $this->assertSame(10.0, ApiRequestStat::percentileFromHistogram($histogram, 50));
        $this->assertSame(100.0, ApiRequestStat::percentileFromHistogram($histogram, 95));
        $this->assertSame(1000.0, ApiRequestStat::percentileFromHistogram($histogram, 99));
        $this->assertSame(10000.0, ApiRequestStat::percentileFromHistogram($histogram, 100));
        $this->assertNull(ApiRequestStat::percentileFromHistogram([], 95));
    }

    public function test_the_histogram_column_for_a_duration(): void
    {
        $this->assertSame('h_le_10', ApiRequestStat::histogramColumnFor(10));
        $this->assertSame('h_le_25', ApiRequestStat::histogramColumnFor(10.01));
        $this->assertSame('h_inf', ApiRequestStat::histogramColumnFor(10000.5));
    }
}
