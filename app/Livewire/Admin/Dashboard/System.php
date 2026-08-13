<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\Dashboard\DashboardMetrics;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * System tab: Application runtime telemetry, database stats, queue worker status,
 * failed jobs monitor, and scheduler heartbeat.
 */
#[Lazy]
class System extends Component
{
    public string $selectedRange;

    public function render(DashboardMetrics $metrics): View
    {
        $range = DateRange::fromValue($this->selectedRange);

        return view('livewire.admin.dashboard.system', [
            'info' => $metrics->remember('system.info', $range, fn (): array => $metrics->system->systemInfo()),
            'dbStats' => $metrics->remember('system.db_stats', $range, fn (): array => $metrics->system->databaseStats()),
            'health' => [
                'queued' => $metrics->system->queuedJobs(),
                'reserved' => $metrics->system->reservedJobs(),
                'failed' => $metrics->system->failedJobs(),
                'recentFailures' => $metrics->system->recentFailures(),
                'lastScheduledRun' => $metrics->system->lastScheduledRunAt(),
                'oldestWait' => $metrics->system->oldestQueuedWaitSeconds(),
            ],
            'recentFailuresList' => $metrics->system->recentFailedJobsList(5),
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.admin.dashboard.placeholder');
    }
}
