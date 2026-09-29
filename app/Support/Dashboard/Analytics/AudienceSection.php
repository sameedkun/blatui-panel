<?php

namespace App\Support\Dashboard\Analytics;

use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\AudienceMetrics;

/** Understand your users — growth, engagement, retention and conversion. */
class AudienceSection extends AnalyticsSection
{
    public function __construct(private readonly AudienceMetrics $audience) {}

    public function key(): string
    {
        return 'audience';
    }

    public function label(): string
    {
        return __('dashboard.sections.audience.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.audience.description');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function permission(): ?string
    {
        return 'users.view';
    }

    public function build(DateRange $range): array
    {
        $previous = $range->previous();
        $labels = array_values($range->buckets());
        $breakdown = $this->audience->breakdown();
        $signups = $this->audience->signupMethods($range);

        return [
            Row::columns(4,
                Metric::make(__('dashboard.metrics.total_users'), $this->audience->totalUsers())
                    ->compareTo($this->audience->totalUsers($range->start->subSecond()))
                    ->icon('users'),
                Metric::make(__('dashboard.metrics.new_users'), $this->audience->newUsers($range))
                    ->compareTo($this->audience->newUsers($previous))
                    ->icon('user-plus'),
                Metric::make(__('dashboard.metrics.active_users'), $this->audience->activeUsers($range))
                    ->compareTo($this->audience->activeUsers($previous))
                    ->description(__('dashboard.metrics.active_users_hint'))
                    ->icon('activity'),
                Metric::make(__('dashboard.metrics.guests'), $this->audience->guests())
                    ->compareTo($this->audience->guests($range->start->subSecond()))
                    ->description(__('dashboard.metrics.new_in_period', ['count' => number_format($this->audience->newGuests($range))]))
                    ->icon('user'),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.user_growth'), Chart::LINE)
                    ->description(__('dashboard.charts.user_growth_hint'))
                    ->icon('trending-up')
                    ->labels($labels)
                    ->series(__('dashboard.series.total_users'), array_values($this->audience->growthSeries($range)))
                    ->colors(['var(--chart-2)']),
            ),

            Row::columns(3,
                Chart::make(__('dashboard.charts.active_users'), Chart::AREA)
                    ->description(__('dashboard.charts.active_users_hint'))
                    ->icon('activity')
                    ->labels($labels)
                    ->series(__('dashboard.series.active_users'), array_values($this->audience->activeSeries($range)))
                    ->colors(['var(--chart-1)'])
                    ->span(2),
                Chart::make(__('dashboard.charts.new_vs_returning'), Chart::BAR)
                    ->description(__('dashboard.charts.new_vs_returning_hint'))
                    ->icon('repeat')
                    ->labels($labels)
                    ->series(__('dashboard.series.new_users'), array_values($this->audience->registrationSeries($range)))
                    ->series(__('dashboard.series.returning_users'), array_values($this->audience->returningSeries($range)))
                    ->colors(['var(--chart-2)', 'var(--chart-3)'])
                    ->stacked(),
            ),

            Row::columns(2,
                BarList::make(__('dashboard.lists.user_breakdown'))
                    ->description(__('dashboard.lists.user_breakdown_hint'))
                    ->icon('pie-chart')
                    ->item(__('dashboard.labels.app_users'), $breakdown['app'], tone: 'success')
                    ->item(__('dashboard.labels.guests'), $breakdown['guests'], tone: 'info')
                    ->item(__('dashboard.labels.staff'), $breakdown['staff'])
                    ->item(__('dashboard.labels.suspended'), $breakdown['banned'], tone: 'danger')
                    ->item(__('dashboard.labels.pending_deletion'), $breakdown['pending_deletion'], tone: 'warning')
                    ->item(__('dashboard.labels.unverified'), $breakdown['unverified'], tone: 'warning')
                    ->withoutShare()
                    ->link(route('admin.users.index'), 'users.view'),
                BarList::make(__('dashboard.lists.signup_methods'))
                    ->description(__('dashboard.lists.signup_methods_hint'))
                    ->icon('log-in')
                    ->item(__('dashboard.labels.email'), $signups['email'])
                    ->item('Google', $signups['google'])
                    ->item('Apple', $signups['apple'])
                    ->sortDescending(),
            ),

            Row::columns(1,
                KeyFigures::make(__('dashboard.figures.user_activity'))
                    ->description(__('dashboard.figures.user_activity_hint'))
                    ->icon('gauge')
                    ->figure(__('dashboard.figures.registration_rate'), $this->audience->registrationRate($range), Format::NUMBER, __('dashboard.figures.per_day'))
                    ->figure(__('dashboard.figures.activation'), $this->audience->activationRate($range), Format::PERCENT, __('dashboard.figures.activation_hint'))
                    ->figure(__('dashboard.figures.retention'), $this->audience->retentionRate($range), Format::PERCENT, __('dashboard.figures.retention_hint'))
                    ->figure(__('dashboard.figures.returning_users'), $this->audience->returningUsers($range), Format::NUMBER, __('dashboard.figures.returning_users_hint'))
                    ->figure(__('dashboard.figures.guest_conversion'), $this->audience->guestConversionRate($range), Format::PERCENT, __('dashboard.figures.guest_conversion_hint', ['count' => number_format($this->audience->guestConversions($range))])),
            ),
        ];
    }
}
