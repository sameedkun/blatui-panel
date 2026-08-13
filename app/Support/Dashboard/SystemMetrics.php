<?php

namespace App\Support\Dashboard;

use App\Enum\ActivityContext;
use App\Models\Feedback;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Operational health of the panel itself — queue, scheduler and audit throughput.
 *
 * This is the closest thing the app currently has to an infrastructure view, and it is
 * intentionally kept separate from the reserved Infrastructure section: everything here
 * is about *this* Laravel app, whereas that section is a placeholder for whatever
 * external estate (VPN nodes, inference workers, …) this panel eventually manages.
 */
class SystemMetrics
{
    /** Jobs sitting in the queue waiting for a worker. */
    public function queuedJobs(): int
    {
        try {
            return Queue::connection()->size();
        } catch (\Throwable) {
            return Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        }
    }

    /** Jobs currently being processed — a non-zero value means workers are alive. */
    public function reservedJobs(): int
    {
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            return DB::table('jobs')->whereNotNull('reserved_at')->count();
        }

        return 0;
    }

    public function failedJobs(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')->count();
    }

    /** Failures recent enough to still be worth acting on. */
    public function recentFailures(int $hours = 24): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->where('failed_at', '>=', Date::now()->subHours($hours))
            ->count();
    }

    /**
     * The oldest job still queued, as a wait time in seconds.
     *
     * `jobs.available_at` is a raw unix timestamp column (not a Laravel datetime), so it
     * is compared numerically rather than through Carbon.
     */
    public function oldestQueuedWaitSeconds(): ?int
    {
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->min('available_at');

            if ($oldest === null) {
                return null;
            }

            return max(0, Date::now()->getTimestamp() - (int) $oldest);
        }

        return null;
    }

    /**
     * When scheduled work last ran.
     *
     * Nothing records scheduler runs directly, but every scheduled job in this app logs
     * its activity with {@see ActivityContext::Scheduler}, so the newest such row is a
     * faithful "the scheduler is alive" signal — and it stays correct automatically as
     * new scheduled jobs are added.
     */
    public function lastScheduledRunAt(): ?CarbonInterface
    {
        $timestamp = Activity::query()
            ->where('properties->context', ActivityContext::Scheduler->value)
            ->max('created_at');

        return $timestamp ? Date::parse($timestamp) : null;
    }

    /** Audit rows written inside the window — overall panel/API throughput. */
    public function activityVolume(DateRange $range): int
    {
        return Activity::query()
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->count();
    }

    /**
     * Audit activity split by originating runtime, busiest first.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function activityByContext(DateRange $range): array
    {
        $labels = [];
        $values = [];

        // One count per context rather than a single GROUP BY on the JSON column: the
        // extraction function needed for that grouping differs per database engine, while
        // Laravel's `properties->context` operator is translated correctly for all of
        // them. The vocabulary is a handful of fixed cases and the result is cached, so
        // the extra round trips cost nothing meaningful.
        foreach (ActivityContext::cases() as $context) {
            $total = Activity::query()
                ->whereBetween('created_at', [$range->start(), $range->end()])
                ->where('properties->context', $context->value)
                ->count();

            // Contexts this deployment never produces would just be flat zeroes on the
            // chart — drop them rather than pad the axis with dead categories.
            if ($total === 0) {
                continue;
            }

            $labels[] = $context->label();
            $values[] = $total;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * The most recent audit entries, for the activity feed.
     *
     * @return Collection<int, Activity>
     */
    public function recentActivity(int $limit = 8)
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /** Feedback submissions nobody has looked at yet. */
    public function unreadFeedback(): int
    {
        return Feedback::query()->whereNull('read_at')->count();
    }

    /**
     * Application runtime environment details.
     *
     * @return array<string, string>
     */
    public function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env', 'production'),
            'db_driver' => config('database.default', 'sqlite'),
            'cache_driver' => config('cache.default', 'file'),
            'queue_driver' => config('queue.default', 'database'),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Core database table row counts.
     *
     * @return array<string, int>
     */
    public function databaseStats(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'subscriptions' => DB::table('subscriptions')->count(),
            'tickets' => DB::table('tickets')->count(),
            'activity_log' => DB::table('activity_log')->count(),
            'blocked_ips' => DB::table('blocked_ips')->count(),
            'user_devices' => DB::table('user_devices')->count(),
        ];
    }

    /**
     * List recent failed jobs for the system monitor.
     *
     * @return Collection<int, object>
     */
    public function recentFailedJobsList(int $limit = 5)
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit($limit)
            ->get();
    }
}
