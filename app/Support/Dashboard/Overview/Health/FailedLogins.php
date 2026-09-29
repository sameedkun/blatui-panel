<?php

namespace App\Support\Dashboard\Overview\Health;

use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\SecurityMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;
use Illuminate\Support\Facades\Date;

/**
 * Failed sign-ins in the last 24 hours, graded against the daily average of
 * the week before — a spike relative to normal matters more than any
 * absolute number, which varies wildly between products.
 */
class FailedLogins extends OverviewWidget
{
    protected ?string $permission = 'activity_logs.view';

    public function __construct(private readonly SecurityMetrics $security) {}

    public function build(DateRange $range): HealthIndicator
    {
        $now = Date::now();
        $today = $this->security->failedLogins(DateRange::exact($now->subDay(), $now));
        $weekAverage = $this->security->failedLogins(DateRange::exact($now->subDays(8), $now->subDay())) / 7;

        $status = match (true) {
            $today >= 50 && $today >= $weekAverage * 5 => HealthIndicator::CRITICAL,
            $today >= 20 && $today >= $weekAverage * 2 => HealthIndicator::WARNING,
            default => HealthIndicator::OK,
        };

        return HealthIndicator::make(__('dashboard.health.failed_logins'), number_format($today), $status, 'shield-alert')
            ->hint(__('dashboard.health.daily_average', ['value' => number_format($weekAverage, 1)]))
            ->link(route('admin.dashboard.analytics', ['tab' => 'security']), 'dashboard.analytics.view');
    }
}
