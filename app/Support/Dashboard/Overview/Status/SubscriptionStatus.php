<?php

namespace App\Support\Dashboard\Overview\Status;

use App\Enum\SubscriptionStatus as Status;
use App\Support\Dashboard\Blocks\StatusPanel;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/** Where subscriptions stand: movement figures up top, the status split beneath. */
class SubscriptionStatus extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly SubscriptionMetrics $subscriptions) {}

    public function build(DateRange $range): StatusPanel
    {
        $breakdown = $this->subscriptions->statusBreakdown();

        return StatusPanel::make(__('dashboard.status.subscriptions'))
            ->description(__('dashboard.status.subscriptions_hint'))
            ->icon('credit-card')
            ->figure(__('dashboard.status.new'), $this->subscriptions->started($range))
            ->figure(__('dashboard.status.churn_rate'), $this->subscriptions->churnRate($range), Format::PERCENT)
            ->figure(__('dashboard.status.trial_conversion'), $this->subscriptions->trialConversionRate($range), Format::PERCENT)
            ->figure(__('dashboard.status.expiring_soon'), $this->subscriptions->expiringSoon())
            ->segmentsTitle(__('dashboard.status.by_status'))
            ->segment(Status::Active->label(), $breakdown[Status::Active->value], 'success')
            ->segment(Status::Trialing->label(), $breakdown[Status::Trialing->value], 'info')
            ->segment(Status::Grace->label(), $breakdown[Status::Grace->value], 'warning')
            ->segment(Status::Cancelled->label(), $breakdown[Status::Cancelled->value], 'danger')
            ->link(route('admin.dashboard.analytics', ['tab' => 'subscriptions']), 'dashboard.analytics.view');
    }
}
