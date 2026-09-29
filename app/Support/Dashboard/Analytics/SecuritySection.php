<?php

namespace App\Support\Dashboard\Analytics;

use App\Enum\DeviceType;
use App\Models\User;
use App\Support\ActivityPresenter;
use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\Feed;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\SecurityMetrics;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;

/**
 * Application and account security — sign-ins, suspensions, blocked IPs and
 * sessions. Gated on the audit log, since it surfaces who did what.
 */
class SecuritySection extends AnalyticsSection
{
    public function __construct(private readonly SecurityMetrics $security) {}

    public function key(): string
    {
        return 'security';
    }

    public function label(): string
    {
        return __('dashboard.sections.security.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.security.description');
    }

    public function icon(): string
    {
        return 'shield';
    }

    public function permission(): ?string
    {
        return 'activity_logs.view';
    }

    public function build(DateRange $range): array
    {
        $previous = $range->previous();
        $auth = $this->security->authSeries($range);
        $buckets = $range->buckets();

        $events = Feed::make(__('dashboard.feeds.security_events'))
            ->description(__('dashboard.feeds.security_events_hint'))
            ->icon('siren')
            ->emptyMessage(__('dashboard.feeds.security_events_empty'))
            ->link(route('admin.activity-logs.index'), 'activity_logs.view')
            ->span(2);

        foreach ($this->security->failedLoginSpikes($range) as $bucket => $count) {
            $events->item(
                title: __('dashboard.feeds.failed_login_spike'),
                description: __('dashboard.feeds.failed_login_spike_hint', ['count' => number_format($count), 'period' => $buckets[$bucket] ?? $bucket]),
                time: Date::parse($range->granularity() === 'month' ? $bucket.'-01' : $bucket)->toIso8601String(),
                icon: 'triangle-alert',
                tone: 'danger',
            );
        }

        $this->security->recentEvents($range)->each(function (Activity $activity) use ($events): void {
            $headline = ActivityPresenter::headline($activity);

            $events->item(
                title: $headline['title'],
                description: collect([
                    $activity->causer instanceof User ? $activity->causer->name : ($activity->properties['email'] ?? null),
                    ActivityPresenter::moduleLabel($activity->properties['module'] ?? null),
                ])->filter()->implode(' · '),
                time: $activity->created_at?->toIso8601String(),
                icon: $headline['icon'],
                tone: $headline['tone'],
            );
        });

        $devices = BarList::make(__('dashboard.lists.active_devices'))
            ->icon('smartphone')
            ->link(route('admin.devices.index'), 'devices.view');

        foreach ($this->security->activeDevicesByType() as $type => $count) {
            $devices->item(DeviceType::from($type)->label(), $count);
        }

        return [
            Row::columns(4,
                Metric::make(__('dashboard.metrics.failed_logins'), $this->security->failedLogins($range))
                    ->compareTo($this->security->failedLogins($previous))
                    ->icon('shield-alert')
                    ->invert(),
                Metric::make(__('dashboard.metrics.suspensions'), $this->security->suspensions($range))
                    ->compareTo($this->security->suspensions($previous))
                    ->description(__('dashboard.metrics.currently_suspended', ['count' => number_format($this->security->currentlySuspended())]))
                    ->icon('shield-ban')
                    ->invert(),
                Metric::make(__('dashboard.metrics.blocked_ips'), $this->security->activeBlockedIps())
                    ->description(__('dashboard.metrics.new_in_period', ['count' => number_format($this->security->newBlockedIps($range))]))
                    ->icon('ban'),
                Metric::make(__('dashboard.metrics.active_sessions'), $this->security->activeSessions())
                    ->description(__('dashboard.metrics.active_sessions_hint'))
                    ->icon('monitor-smartphone'),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.authentication'), Chart::AREA)
                    ->description(__('dashboard.charts.authentication_hint'))
                    ->icon('key-round')
                    ->labels(array_values($buckets))
                    ->series(__('dashboard.series.successful'), array_values($auth['success']))
                    ->series(__('dashboard.series.failed'), array_values($auth['failed']))
                    ->colors(['var(--chart-2)', 'var(--destructive)']),
            ),

            Row::columns(2,
                BarList::make(__('dashboard.lists.login_activity'))
                    ->icon('log-in')
                    ->item(__('dashboard.labels.successful'), $this->security->successfulLogins($range), tone: 'success')
                    ->item(__('dashboard.labels.failed'), $this->security->failedLogins($range), tone: 'danger')
                    ->item(__('dashboard.labels.passkey'), $this->security->passkeyLogins($range), __('dashboard.labels.passkey_hint'), 'info'),
                BarList::make(__('dashboard.lists.account_events'))
                    ->icon('user-cog')
                    ->item(__('dashboard.labels.suspensions'), $this->security->suspensions($range), tone: 'danger')
                    ->item(__('dashboard.labels.password_changes'), $this->security->passwordChanges($range))
                    ->item(__('dashboard.labels.password_resets'), $this->security->passwordResets($range))
                    ->item(__('dashboard.labels.new_devices'), $this->security->newDevices($range), tone: 'info')
                    ->item(__('dashboard.labels.devices_revoked'), $this->security->revokedDevices($range), tone: 'warning')
                    ->item(__('dashboard.labels.devices_blocked'), $this->security->blockedDevices($range), tone: 'danger')
                    ->withoutShare(),
            ),

            Row::columns(3, $events, $devices),
        ];
    }
}
