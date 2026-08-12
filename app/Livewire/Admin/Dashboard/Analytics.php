<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\Dashboard\DashboardMetrics;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Trends over the selected range — how things are moving, rather than where they stand.
 *
 * Every payload here is permission-gated before it is even queried, so a viewer without
 * `subscriptions.view` never pays for the revenue aggregate, let alone sees it.
 */
#[Lazy]
class Analytics extends Component
{
    public string $selectedRange;

    public function render(DashboardMetrics $metrics): View
    {
        $range = DateRange::fromValue($this->selectedRange);
        $user = auth()->user();

        return view('livewire.admin.dashboard.analytics', [
            'revenue' => $user->can('subscriptions.view')
                ? $metrics->remember('analytics.revenue', $range, fn (): array => $metrics->revenue->revenueSeries($range))
                : null,
            'churn' => $user->can('subscriptions.view')
                ? $metrics->remember('analytics.churn', $range, fn (): array => $metrics->revenue->churnSeries($range))
                : null,
            'tickets' => $user->can('tickets.view')
                ? $metrics->remember('analytics.tickets', $range, fn (): array => $metrics->support->volumeSeries($range))
                : null,
            'devices' => $user->can('devices.view')
                ? $metrics->remember('analytics.devices', $range, fn (): array => $metrics->security->registrationSeries($range))
                : null,
            'deviceTypes' => $user->can('devices.view')
                ? $metrics->remember('analytics.device_types', $range, fn (): array => $metrics->security->deviceTypeBreakdown())
                : null,
            'platforms' => $user->can('devices.view')
                ? $metrics->remember('analytics.platforms', $range, fn (): array => $metrics->security->platformBreakdown())
                : null,
            'countries' => $user->can('devices.view')
                ? $metrics->remember('analytics.countries', $range, fn (): array => $metrics->audience->topCountries())
                : null,
            'contexts' => $user->can('activity_logs.view')
                ? $metrics->remember('analytics.contexts', $range, fn (): array => $metrics->system->activityByContext($range))
                : null,
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.admin.dashboard.placeholder');
    }
}
