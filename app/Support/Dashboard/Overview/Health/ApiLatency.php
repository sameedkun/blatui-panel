<?php

namespace App\Support\Dashboard\Overview\Health;

use App\Support\ApiLogs\ApiLogAnalytics;
use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Overview\OverviewWidget;

/** p95 API latency over the last 24 hours. */
class ApiLatency extends OverviewWidget
{
    protected ?string $permission = 'api_logs.analytics.view';

    public function __construct(private readonly ApiLogAnalytics $analytics) {}

    public function build(DateRange $range): HealthIndicator
    {
        $kpis = $this->analytics->kpis($this->analytics->range('24h'));
        $p95 = $kpis['p95_ms'];

        return HealthIndicator::make(
            __('dashboard.health.api_latency'),
            $p95 === null ? '—' : __('dashboard.health.ms', ['value' => number_format($p95)]),
            HealthIndicator::grade($p95, (int) config('dashboard.health.latency_warning_ms'), (int) config('dashboard.health.latency_critical_ms')),
            'timer',
        )
            ->hint($kpis['p50_ms'] === null ? null : __('dashboard.health.median', ['value' => number_format($kpis['p50_ms'])]))
            ->link(route('admin.api-logs.analytics'), 'api_logs.analytics.view');
    }
}
