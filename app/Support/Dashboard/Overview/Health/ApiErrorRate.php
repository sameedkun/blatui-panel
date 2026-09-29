<?php

namespace App\Support\Dashboard\Overview\Health;

use App\Support\ApiLogs\ApiLogAnalytics;
use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Overview\OverviewWidget;

/** Share of API requests answered with a 5xx over the last 24 hours. */
class ApiErrorRate extends OverviewWidget
{
    protected ?string $permission = 'api_logs.analytics.view';

    public function __construct(private readonly ApiLogAnalytics $analytics) {}

    public function build(DateRange $range): HealthIndicator
    {
        $kpis = $this->analytics->kpis($this->analytics->range('24h'));
        $hasTraffic = $kpis['requests'] > 0;

        return HealthIndicator::make(
            __('dashboard.health.api_errors'),
            $hasTraffic ? Format::value($kpis['rate_5xx'], Format::PERCENT) : '—',
            $hasTraffic ? HealthIndicator::grade($kpis['rate_5xx'], 1, 5) : HealthIndicator::UNKNOWN,
            'server-crash',
        )
            ->hint(__('dashboard.health.api_requests', ['count' => number_format($kpis['requests'])]))
            ->link(route('admin.api-logs.analytics'), 'api_logs.analytics.view');
    }
}
