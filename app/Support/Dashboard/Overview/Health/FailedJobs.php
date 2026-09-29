<?php

namespace App\Support\Dashboard\Overview\Health;

use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Overview\OverviewWidget;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Queue jobs that failed in the last 24 hours — scheduled sweeps, report
 * generation, push broadcasts. A failure here usually means something the
 * panel promised to do quietly did not happen.
 */
class FailedJobs extends OverviewWidget
{
    public function build(DateRange $range): ?HealthIndicator
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        if (! Schema::hasTable($table)) {
            return null;
        }

        $failed = DB::table($table)->where('failed_at', '>=', Date::now()->subDay())->count();

        return HealthIndicator::make(
            __('dashboard.health.failed_jobs'),
            number_format($failed),
            HealthIndicator::grade($failed, 1, 10),
            'layers',
        )->hint(__('dashboard.health.failed_jobs_total', ['count' => number_format(DB::table($table)->count())]));
    }
}
