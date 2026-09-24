<?php

namespace App\Livewire\Admin\Administration\ApiLogs;

use App\Support\ApiLogs\AnalyticsRange;
use App\Support\ApiLogs\ApiLogAnalytics;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Traffic, latency and errors across the API for a selectable window — from
 * a live, auto-refreshing last hour down to all time. Every number comes from
 * {@see ApiLogAnalytics}, which stitches rollup tiers and the raw tail
 * together; this component only picks the range and hands payloads to the view.
 */
#[Layout('layouts.admin.app')]
class Analytics extends Component
{
    #[Url]
    public string $range = AnalyticsRange::DEFAULT;

    public function mount(): void
    {
        $this->authorize('api_logs.analytics.view');

        if (! in_array($this->range, AnalyticsRange::KEYS, true)) {
            $this->range = AnalyticsRange::DEFAULT;
        }
    }

    public function selectRange(string $range): void
    {
        if (in_array($range, AnalyticsRange::KEYS, true)) {
            $this->range = $range;
        }
    }

    public function render(ApiLogAnalytics $analytics): View
    {
        $range = $analytics->range($this->range);

        return view('livewire.admin.administration.api-logs.analytics', [
            'selected' => $range,
            'rangeOptions' => AnalyticsRange::options(),
            'kpis' => $analytics->kpis($range),
            'requestsOverTime' => $analytics->requestsOverTime($range),
            'latencyOverTime' => $analytics->latencyOverTime($range),
            'statusCodes' => $analytics->statusCodes($range),
            'methods' => $analytics->methods($range),
            'versions' => $analytics->versions($range),
            'clientTypes' => $analytics->clientTypes($range),
            'slowest' => $analytics->slowestEndpoints($range),
            'erroring' => $analytics->erroringEndpoints($range),
            'busiest' => $analytics->busiestEndpoints($range),
            'errorCodes' => $analytics->errorCodes($range),
            'topIps' => $analytics->topIps($range),
            'topUsers' => $analytics->topUsers($range),
            'topExceptions' => $analytics->topExceptions($range),
        ])->title(__('api_logs.analytics.title'));
    }
}
