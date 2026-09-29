<?php

namespace App\Support\Dashboard\Overview;

use App\Models\User;
use App\Support\ActivityPresenter;
use App\Support\Dashboard\Blocks\Feed;
use App\Support\Dashboard\DateRange;
use Spatie\Activitylog\Models\Activity;

/** The latest audit entries across the whole panel, newest first. */
class RecentActivity extends OverviewWidget
{
    protected ?string $permission = 'activity_logs.view';

    private const int LIMIT = 8;

    public function build(DateRange $range): Feed
    {
        $feed = Feed::make(__('dashboard.activity.title'))
            ->description(__('dashboard.activity.hint'))
            ->icon('history')
            ->emptyMessage(__('dashboard.activity.empty'))
            ->link(route('admin.activity-logs.index'), 'activity_logs.view');

        Activity::query()
            ->with('causer')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->each(function (Activity $activity) use ($feed): void {
                $headline = ActivityPresenter::headline($activity);

                $feed->item(
                    title: $headline['title'],
                    description: collect([
                        $activity->causer instanceof User ? $activity->causer->name : __('dashboard.activity.system'),
                        ActivityPresenter::moduleLabel($activity->properties['module'] ?? null),
                    ])->filter()->implode(' · '),
                    time: $activity->created_at?->toIso8601String(),
                    icon: $headline['icon'],
                    tone: $headline['tone'],
                );
            });

        return $feed;
    }
}
