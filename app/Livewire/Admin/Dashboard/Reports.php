<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\Dashboard\DashboardMetrics;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Current-state breakdowns and record listings — the "who and what", not the trend.
 *
 * Rows here are Eloquent models and are deliberately never cached: a recent-subscriptions
 * table that is five minutes stale is worse than one that costs a query.
 */
#[Lazy]
class Reports extends Component
{
    public string $selectedRange;

    public function render(DashboardMetrics $metrics): View
    {
        $range = DateRange::fromValue($this->selectedRange);
        $user = auth()->user();

        $billing = $user->can('subscriptions.view');
        $tickets = $user->can('tickets.view');

        return view('livewire.admin.dashboard.reports', [
            'plans' => $user->can('plans.view')
                ? $metrics->remember('reports.plans', $range, fn (): array => $metrics->revenue->planDistribution())
                : null,
            'statuses' => $billing
                ? $metrics->remember('reports.statuses', $range, fn (): array => $metrics->revenue->statusBreakdown())
                : null,
            'conversion' => $billing
                ? $metrics->remember('reports.conversion', $range, fn (): array => [
                    'rate' => $metrics->revenue->trialConversionRate(),
                    'arpu' => $metrics->revenue->averageRevenuePerUser(),
                    'lifetime' => $metrics->revenue->lifetimeRevenue(),
                ])
                : null,
            'subscriptions' => $billing ? $metrics->revenue->recentSubscriptions() : null,
            'priorities' => $tickets
                ? $metrics->remember('reports.priorities', $range, fn (): array => $metrics->support->priorityBreakdown())
                : null,
            'workload' => $user->can('tickets.manage')
                ? [
                    'agents' => $metrics->support->agentWorkload(),
                    'unassigned' => $metrics->support->unassignedTickets(),
                    'medianResponse' => $metrics->support->medianFirstResponseHours($range),
                ]
                : null,
            'oldestTickets' => $tickets ? $metrics->support->oldestOpen() : null,
            'blocks' => $user->can('blocked-ips.view')
                ? [
                    'rows' => $metrics->security->busiestBlocks(),
                    'active' => $metrics->security->activeBlocks(),
                    'global' => $metrics->security->globalBlocks(),
                    'hits' => $metrics->security->blockedHits(),
                ]
                : null,
            'risk' => $user->can('devices.investigate')
                ? [
                    'shared' => $metrics->security->sharedFingerprints(),
                    'blocked' => $metrics->security->blockedDevices(),
                    'revoked' => $metrics->security->revokedDevices(),
                ]
                : null,
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.admin.dashboard.placeholder');
    }
}
