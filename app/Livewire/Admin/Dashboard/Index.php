<?php

namespace App\Livewire\Admin\Dashboard;

use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Dashboard\Concerns\HasDashboardRange;
use App\Support\Dashboard\Blocks\Block;
use App\Support\Dashboard\Contracts\Widget;
use App\Support\Dashboard\DashboardCache;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard → Overview: "the state of my application in ten seconds".
 *
 * Renders the Overview slots from {@see DashboardRegistry} — KPIs, the two
 * main trends, business status, activity + actions, health — each widget
 * filtered by its own permission and cached per range. The page owns only
 * the layout and the range; what fills it is config.
 */
#[Layout('layouts.admin.app')]
class Index extends Component
{
    use HasDashboardRange, HasToast;

    public function render(DashboardRegistry $registry, DashboardCache $cache): View
    {
        $range = $this->dateRange();

        return view('livewire.admin.dashboard.index', [
            'overview' => $this->buildSlots($registry, $cache, $range),
            'selectedRange' => $range,
            'rangeOptions' => DateRange::options(),
        ])->title(__('dashboard.overview.title'));
    }

    /**
     * Every slot's built blocks, dropping widgets with nothing to show.
     *
     * @return array<string, list<Block>>
     */
    protected function buildSlots(DashboardRegistry $registry, DashboardCache $cache, DateRange $range): array
    {
        $slots = [];

        foreach ($registry->overview(auth()->user()) as $slot => $widgets) {
            $slots[$slot] = array_values(array_filter(array_map(
                fn (Widget $widget): ?Block => $cache->remember("overview.{$widget->key()}", $range, fn (): ?Block => $widget->build($range)),
                $widgets,
            )));
        }

        return $slots;
    }
}
