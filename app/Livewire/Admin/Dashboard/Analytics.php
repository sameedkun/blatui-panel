<?php

namespace App\Livewire\Admin\Dashboard;

use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Dashboard\Concerns\HasDashboardRange;
use App\Support\Dashboard\Analytics\AnalyticsSection;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DashboardCache;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard → Analytics: "let me investigate the numbers".
 *
 * One tab per {@see AnalyticsSection} registered in config('dashboard.analytics'),
 * filtered by each section's own permission. Only the active tab is built,
 * and its rows are cached per range. The page has no knowledge of any
 * particular domain — every tab is rendered by the same generic row/block
 * renderer.
 */
#[Layout('layouts.admin.app')]
class Analytics extends Component
{
    use HasDashboardRange, HasToast;

    #[Url]
    public string $tab = '';

    public function mount(): void
    {
        $this->authorize('dashboard.analytics.view');
    }

    /** Switch tabs, guarded to sections the viewer may open so a crafted call can't reach a hidden one. */
    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, $this->sections())) {
            $this->tab = $tab;
        }
    }

    public function render(DashboardCache $cache): View
    {
        $sections = $this->sections();
        $active = $sections[$this->tab] ?? reset($sections) ?: null;
        $range = $this->dateRange();

        return view('livewire.admin.dashboard.analytics', [
            'sections' => $sections,
            'active' => $active,
            'rows' => $active !== null && $active->supports($range) ? $this->buildRows($active, $cache, $range) : [],
            'selectedRange' => $range,
            'rangeOptions' => DateRange::options(),
        ])->title($active !== null ? __('dashboard.analytics.page_title', ['section' => $active->label()]) : __('dashboard.analytics.title'));
    }

    /** @return array<string, AnalyticsSection> */
    protected function sections(): array
    {
        return app(DashboardRegistry::class)->sections(auth()->user());
    }

    /** @return list<Row> */
    protected function buildRows(AnalyticsSection $section, DashboardCache $cache, DateRange $range): array
    {
        return $cache->remember("analytics.{$section->key()}", $range, fn (): array => $section->build($range));
    }
}
