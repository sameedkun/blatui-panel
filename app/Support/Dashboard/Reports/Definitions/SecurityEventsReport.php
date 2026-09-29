<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Models\User;
use App\Support\ActivityPresenter;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SecurityMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/** Security-relevant audit events in the period — failed sign-ins, suspensions, blocks, revocations. */
class SecurityEventsReport extends ReportDefinition
{
    public function __construct(private readonly SecurityMetrics $security) {}

    public function key(): string
    {
        return 'security_events';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.security_events.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.security_events.description');
    }

    public function icon(): string
    {
        return 'shield';
    }

    public function permission(): ?string
    {
        return 'activity_logs.view';
    }

    public function filters(): array
    {
        return [
            ReportFilter::select('event', __('dashboard.reports.filters.event'), collect(SecurityMetrics::SECURITY_EVENTS)
                ->mapWithKeys(fn (string $event): array => [$event => ActivityPresenter::actionLabel($event)])
                ->all()),
        ];
    }

    public function columns(): array
    {
        return [
            'time' => __('dashboard.reports.columns.time'),
            'event' => __('dashboard.reports.columns.event'),
            'module' => __('dashboard.reports.columns.module'),
            'account' => __('dashboard.reports.columns.account'),
            'performed_by' => __('dashboard.reports.columns.performed_by'),
            'ip' => __('dashboard.reports.columns.ip'),
            'detail' => __('dashboard.reports.columns.detail'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        foreach ($this->query($range, $filters)->with(['causer', 'subject'])->lazyById(500) as $activity) {
            $properties = $activity->properties;

            yield [
                'time' => $activity->created_at?->format('Y-m-d H:i:s'),
                'event' => ActivityPresenter::headline($activity)['title'],
                'module' => ActivityPresenter::moduleLabel($properties['module'] ?? null),
                'account' => $activity->subject instanceof User ? $activity->subject->email : ($properties['email'] ?? null),
                'performed_by' => $activity->causer instanceof User ? $activity->causer->name : null,
                'ip' => $properties['ip'] ?? null,
                'detail' => $properties['reason'] ?? $properties['ban_reason'] ?? null,
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        return [
            __('dashboard.reports.summary.events') => Format::value($this->query($range, $filters)->count()),
            __('dashboard.reports.summary.failed_logins') => Format::value($this->security->failedLogins($range)),
            __('dashboard.reports.summary.suspensions') => Format::value($this->security->suspensions($range)),
            __('dashboard.reports.summary.blocked_ips') => Format::value($this->security->newBlockedIps($range)),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Activity>
     */
    private function query(DateRange $range, array $filters): Builder
    {
        return Activity::query()
            ->whereIn('event', $filters['event'] ?? null ? [$filters['event']] : SecurityMetrics::SECURITY_EVENTS)
            ->whereBetween('created_at', [$range->start, $range->end]);
    }
}
