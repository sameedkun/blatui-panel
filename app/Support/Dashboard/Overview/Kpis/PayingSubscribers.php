<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Enum\SubscriptionStatus;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;
use Illuminate\Support\Facades\Date;

/** Subscriptions in a paid period now, against the start of the window. */
class PayingSubscribers extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(
        private readonly RevenueMetrics $revenue,
        private readonly SubscriptionMetrics $subscriptions,
    ) {}

    public function build(DateRange $range): Metric
    {
        return Metric::make(__('dashboard.kpis.paying_subscribers'), $this->revenue->liveCustomersAt(Date::now())->count())
            ->compareTo($this->revenue->liveCustomersAt($range->start)->count())
            ->description(__('dashboard.kpis.trialing', ['count' => number_format($this->subscriptions->countByStatus(SubscriptionStatus::Trialing))]))
            ->icon('credit-card')
            ->link(route('admin.subscriptions.index'), 'subscriptions.view');
    }
}
