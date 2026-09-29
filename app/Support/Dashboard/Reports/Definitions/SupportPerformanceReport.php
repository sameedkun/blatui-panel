<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SupportMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;

/** One row per agent: workload and speed for the tickets opened in the period. */
class SupportPerformanceReport extends ReportDefinition
{
    public function __construct(private readonly SupportMetrics $support) {}

    public function key(): string
    {
        return 'support_performance';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.support_performance.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.support_performance.description');
    }

    public function icon(): string
    {
        return 'user-check';
    }

    public function permission(): ?string
    {
        return 'tickets.view';
    }

    public function columns(): array
    {
        return [
            'agent' => __('dashboard.columns.agent'),
            'assigned' => __('dashboard.columns.assigned'),
            'resolved' => __('dashboard.columns.resolved'),
            'open' => __('dashboard.columns.open'),
            'first_response' => __('dashboard.columns.first_response'),
            'resolution' => __('dashboard.columns.resolution'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        foreach ($this->support->agentPerformance($range, PHP_INT_MAX) as $row) {
            yield [
                ...$row,
                'first_response' => $row['first_response'] === null ? null : Format::duration($row['first_response']),
                'resolution' => $row['resolution'] === null ? null : Format::duration($row['resolution']),
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        $times = $this->support->responseTimes($range);

        return [
            __('dashboard.reports.summary.tickets') => Format::value($this->support->created($range)),
            __('dashboard.reports.summary.resolved') => Format::value($this->support->resolved($range)),
            __('dashboard.reports.summary.first_response') => Format::value($times['first_response'], Format::DURATION),
            __('dashboard.reports.summary.resolution') => Format::value($times['resolution'], Format::DURATION),
        ];
    }
}
