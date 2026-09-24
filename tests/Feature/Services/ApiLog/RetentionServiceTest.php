<?php

namespace Tests\Feature\Services\ApiLog;

use App\Enum\ApiStatsPeriod;
use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestPayload;
use App\Models\ApiLog\ApiRequestStat;
use App\Services\ApiLog\RetentionService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-23 12:30:00'));
    }

    private function prune(): array
    {
        return app(RetentionService::class)->prune();
    }

    private function rawLog(CarbonInterface $at): ApiRequestLog
    {
        $log = ApiRequestLog::factory()->create(['created_at' => $at, 'has_payload' => true]);

        ApiRequestPayload::create(['request_id' => $log->request_id, 'query' => ['a' => 1], 'created_at' => $at]);

        return $log;
    }

    private function stat(ApiStatsPeriod $period, CarbonInterface $bucket): ApiRequestStat
    {
        return ApiRequestStat::factory()->period($period, $bucket)->create();
    }

    public function test_raw_logs_and_payloads_older_than_seven_days_are_pruned_once_aggregated(): void
    {
        $this->stat(ApiStatsPeriod::Hour, now()->startOfHour()->subHour());
        $old = $this->rawLog(now()->subDays(8));
        $recent = $this->rawLog(now()->subDays(6));

        $result = $this->prune();

        $this->assertSame(1, $result['raw']);
        $this->assertSame(1, $result['payloads']);
        $this->assertNull($old->fresh());
        $this->assertNull(ApiRequestPayload::find($old->request_id));
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull(ApiRequestPayload::find($recent->request_id));
    }

    public function test_raw_logs_are_never_pruned_before_anything_has_been_aggregated(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'raw API request logs'));

        $old = $this->rawLog(now()->subDays(8));

        $this->assertSame(0, $this->prune()['raw']);
        $this->assertNotNull($old->fresh());
    }

    public function test_raw_logs_are_never_pruned_past_the_last_aggregated_hour(): void
    {
        $lastAggregatedHour = now()->subDays(10)->startOfDay();
        $this->stat(ApiStatsPeriod::Hour, $lastAggregatedHour);

        $aggregated = $this->rawLog($lastAggregatedHour->addMinutes(30));
        $notYetAggregated = $this->rawLog(now()->subDays(9));

        $this->prune();

        $this->assertNull($aggregated->fresh());
        $this->assertNotNull($notYetAggregated->fresh());
    }

    public function test_exceptions_are_kept_for_thirty_days(): void
    {
        $old = ApiRequestException::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = ApiRequestException::factory()->create(['created_at' => now()->subDays(29)]);

        $this->assertSame(1, $this->prune()['exceptions']);
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
    }

    public function test_hourly_stats_are_pruned_after_ninety_days_once_rolled_into_days(): void
    {
        $this->stat(ApiStatsPeriod::Day, now()->startOfDay()->subDay());
        $old = $this->stat(ApiStatsPeriod::Hour, now()->subDays(91)->startOfHour());
        $recent = $this->stat(ApiStatsPeriod::Hour, now()->subDays(89)->startOfHour());

        $this->assertSame(1, $this->prune()['hour']);
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
    }

    public function test_daily_stats_are_pruned_after_a_year_once_rolled_into_months_and_months_are_kept_forever(): void
    {
        $month = $this->stat(ApiStatsPeriod::Month, now()->subYears(5)->startOfMonth());
        $this->stat(ApiStatsPeriod::Month, now()->startOfMonth()->subMonth());
        $old = $this->stat(ApiStatsPeriod::Day, now()->subDays(400)->startOfDay());
        $recent = $this->stat(ApiStatsPeriod::Day, now()->subDays(300)->startOfDay());

        $this->assertSame(1, $this->prune()['day']);
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($month->fresh());
    }

    public function test_hourly_stats_are_kept_while_days_have_not_been_rolled_up(): void
    {
        Log::shouldReceive('warning')->once();

        $old = $this->stat(ApiStatsPeriod::Hour, now()->subDays(91)->startOfHour());

        $this->assertSame(0, $this->prune()['hour']);
        $this->assertNotNull($old->fresh());
    }
}
