<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasToast;
use App\Support\Dashboard\DashboardMetrics;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The dashboard shell.
 *
 * Owns nothing but the tab bar and the shared time range — each tab is its own lazily
 * loaded component that runs only its own queries, so opening the dashboard costs one
 * tab's worth of work rather than every chart on the page.
 *
 * {@see tabs()} is the extension point: adding an area (a VPN fleet, inference usage, …)
 * is one entry here plus that tab's component and view.
 */
#[Layout('layouts.admin.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use HasToast;

    #[Url(as: 'tab', keep: true)]
    public string $tab = 'overview';

    #[Url(as: 'range', keep: true)]
    public string $selectedRange = DateRange::Month->value;

    /**
     * @return array<int, array{key: string, label: string, icon: string, component: string, permission: ?string}>
     */
    public function tabs(): array
    {
        return [
            [
                'key' => 'overview',
                'label' => __('dashboard.tabs.overview'),
                'icon' => 'layout-dashboard',
                'component' => 'admin.dashboard.overview',
                'permission' => null,
            ],
            [
                'key' => 'analytics',
                'label' => __('dashboard.tabs.analytics'),
                'icon' => 'chart-line',
                'component' => 'admin.dashboard.analytics',
                'permission' => null,
            ],
            [
                'key' => 'reports',
                'label' => __('dashboard.tabs.reports'),
                'icon' => 'file-text',
                'component' => 'admin.dashboard.reports',
                'permission' => null,
            ],
            [
                'key' => 'infrastructure',
                'label' => __('dashboard.tabs.infrastructure'),
                'icon' => 'server',
                'component' => 'admin.dashboard.infrastructure',
                'permission' => null,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function visibleTabs(): array
    {
        $user = auth()->user();

        return collect($this->tabs())
            ->filter(fn (array $tab): bool => $tab['permission'] === null || $user->can($tab['permission']))
            ->values()
            ->all();
    }

    /** The tab actually being rendered — falls back if the URL names an unknown one. */
    public function activeTab(): array
    {
        $tabs = $this->visibleTabs();

        return collect($tabs)->firstWhere('key', $this->tab) ?? $tabs[0];
    }

    public function selectTab(string $key): void
    {
        $this->tab = $key;
    }

    public function selectRange(string $value): void
    {
        $this->selectedRange = DateRange::fromValue($value)->value;
    }

    /**
     * Drop this range's cached payloads so the active tab re-queries.
     *
     * Cache keys are namespaced per tab (`overview.*`, `analytics.*`, …), so flushing is a
     * matter of forgetting every key each tab registers — listed here rather than derived,
     * since the tabs own their own keys.
     */
    public function refreshMetrics(DashboardMetrics $metrics): void
    {
        $range = DateRange::fromValue($this->selectedRange);

        $metrics->forget($range, [
            'overview.kpis', 'overview.signups', 'overview.split',
            'analytics.revenue', 'analytics.churn', 'analytics.tickets', 'analytics.devices',
            'analytics.device_types', 'analytics.platforms', 'analytics.countries', 'analytics.contexts',
            'reports.plans', 'reports.statuses', 'reports.conversion', 'reports.priorities',
        ]);

        $this->toastSuccess(__('dashboard.refreshed'));
    }

    public function render(): View
    {
        return view('livewire.admin.dashboard', [
            'tabs' => $this->visibleTabs(),
            'active' => $this->activeTab(),
            'rangeOptions' => DateRange::options(),
            'rangeLabel' => DateRange::fromValue($this->selectedRange)->label(),
        ])->title(__('dashboard.title'));
    }
}
