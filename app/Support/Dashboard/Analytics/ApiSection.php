<?php

namespace App\Support\Dashboard\Analytics;

use App\Support\ApiLogs\AnalyticsRange;
use App\Support\ApiLogs\ApiLogAnalytics;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\Blocks\Table;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;

/**
 * API traffic, errors and latency — a summary view over the same
 * {@see ApiLogAnalytics} the full API Logs analytics page uses, so both always
 * agree. That page stays the place to investigate (minute-level live ranges,
 * exceptions, clients); this tab links to it.
 */
class ApiSection extends AnalyticsSection
{
    /** Dashboard presets → the API analytics range answering the same window. */
    private const array RANGES = ['7d' => '7d', '30d' => '30d', '90d' => '90d', '12m' => '1y'];

    public function __construct(private readonly ApiLogAnalytics $analytics) {}

    public function key(): string
    {
        return 'api';
    }

    public function label(): string
    {
        return __('dashboard.sections.api.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.api.description');
    }

    public function icon(): string
    {
        return 'activity';
    }

    public function permission(): ?string
    {
        return 'api_logs.analytics.view';
    }

    public function supports(DateRange $range): bool
    {
        return isset(self::RANGES[$range->key]);
    }

    public function build(DateRange $range): array
    {
        $apiRange = $this->analytics->range(self::RANGES[$range->key] ?? AnalyticsRange::DEFAULT);
        $kpis = $this->analytics->kpis($apiRange);
        $requests = $this->analytics->requestsOverTime($apiRange);
        $latency = $this->analytics->latencyOverTime($apiRange);
        $analyticsUrl = route('admin.api-logs.analytics', ['range' => $apiRange->key]);

        $endpointTable = fn (string $title, string $icon, array $rows): Table => Table::make($title)
            ->icon($icon)
            ->column('endpoint', __('dashboard.columns.endpoint'))
            ->column('requests', __('dashboard.columns.requests'), Format::NUMBER)
            ->column('error_rate', __('dashboard.columns.error_rate'), Format::PERCENT)
            ->column('p95', __('dashboard.columns.p95'), Format::TEXT, 'right')
            ->rows(array_map(fn (array $row): array => [
                'endpoint' => $row['method'].' '.$row['route_uri'],
                'requests' => $row['requests'],
                'error_rate' => $row['error_rate'],
                'p95' => $row['p95_ms'] === null ? null : __('dashboard.health.ms', ['value' => number_format($row['p95_ms'])]),
            ], $rows))
            ->link($analyticsUrl, 'api_logs.analytics.view');

        $classColors = [2 => 'var(--chart-2)', 3 => 'var(--chart-3)', 4 => 'var(--chart-4)', 5 => 'var(--chart-5)'];
        $requestsChart = Chart::make(__('dashboard.charts.api_requests'), Chart::BAR)
            ->description(__('dashboard.charts.api_requests_hint'))
            ->icon('chart-column')
            ->labels($requests['labels'])
            ->colors(array_values($classColors))
            ->stacked()
            ->link($analyticsUrl, 'api_logs.analytics.view');

        foreach (array_keys($classColors) as $class) {
            $requestsChart->series(__('dashboard.series.status_class', ['class' => $class]), $requests['series'][$class]);
        }

        return [
            Row::columns(4,
                Metric::make(__('dashboard.metrics.api_requests'), $kpis['requests'])
                    ->description(__('dashboard.metrics.per_minute', ['count' => number_format($kpis['per_minute'], 1)]))
                    ->icon('activity'),
                Metric::make(__('dashboard.metrics.server_errors'), $kpis['rate_5xx'], Format::PERCENT)
                    ->description(__('dashboard.metrics.of_requests', ['count' => number_format($kpis['errors_5xx'])]))
                    ->icon('server-crash'),
                Metric::make(__('dashboard.metrics.client_errors'), $kpis['rate_4xx'], Format::PERCENT)
                    ->description(__('dashboard.metrics.of_requests', ['count' => number_format($kpis['errors_4xx'])]))
                    ->icon('triangle-alert'),
                Metric::make(__('dashboard.metrics.p95_latency'), $kpis['p95_ms'] === null ? null : __('dashboard.health.ms', ['value' => number_format($kpis['p95_ms'])]), Format::TEXT)
                    ->description($kpis['p50_ms'] === null ? null : __('dashboard.health.median', ['value' => number_format($kpis['p50_ms'])]))
                    ->icon('timer'),
            ),

            Row::columns(1, $requestsChart),

            Row::columns(1,
                Chart::make(__('dashboard.charts.api_latency'), Chart::LINE)
                    ->description(__('dashboard.charts.api_latency_hint'))
                    ->icon('timer')
                    ->labels($latency['labels'])
                    ->series(__('dashboard.series.avg'), $latency['avg'])
                    ->series(__('dashboard.series.p50'), $latency['p50'])
                    ->series(__('dashboard.series.p95'), $latency['p95'])
                    ->colors(['var(--chart-1)', 'var(--chart-2)', 'var(--chart-5)']),
            ),

            Row::columns(2,
                $endpointTable(__('dashboard.tables.busiest_endpoints'), 'zap', $this->analytics->busiestEndpoints($apiRange, 8)),
                $endpointTable(__('dashboard.tables.erroring_endpoints'), 'shield-alert', $this->analytics->erroringEndpoints($apiRange, 8)),
            ),
        ];
    }
}
