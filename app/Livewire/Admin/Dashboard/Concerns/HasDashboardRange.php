<?php

namespace App\Livewire\Admin\Dashboard\Concerns;

use App\Livewire\Admin\Concerns\HasToast;
use App\Support\Dashboard\DashboardCache;
use App\Support\Dashboard\DateRange;
use Livewire\Attributes\Url;

/**
 * The range picker + Refresh button shared by the dashboard's Overview and
 * Analytics pages, so both headers behave identically. Requires HasToast.
 *
 * @mixin HasToast
 */
trait HasDashboardRange
{
    /** Preset key — URL-bound so a view of the numbers is shareable. */
    #[Url]
    public string $range = DateRange::DEFAULT;

    public function mountHasDashboardRange(): void
    {
        if (! DateRange::isPreset($this->range)) {
            $this->range = DateRange::DEFAULT;
        }
    }

    public function selectRange(string $range): void
    {
        if (DateRange::isPreset($range)) {
            $this->range = $range;
        }
    }

    /** Drop every cached dashboard payload so the next render recomputes it. */
    public function refresh(DashboardCache $cache): void
    {
        $cache->flush();

        $this->toastSuccess(__('dashboard.refreshed'));
    }

    protected function dateRange(): DateRange
    {
        return DateRange::preset($this->range);
    }
}
