<?php

namespace App\Livewire\Admin\Management\Devices;

use App\Models\UserDevice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Account-sharing report: every device fingerprint currently attached to
 * more than one distinct account. A genuinely different query shape than the
 * Devices index (grouped/aggregated, not per-row), so it gets its own page
 * rather than being squeezed into a filter there. Gated separately behind
 * `devices.investigate` since it exposes the fingerprint hash itself.
 */
#[Layout('layouts.admin.app')]
class SharedFingerprints extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('devices.investigate');
    }

    protected function groups(): LengthAwarePaginator
    {
        return UserDevice::query()
            ->select('device_fingerprint')
            ->selectRaw('COUNT(DISTINCT user_id) as user_count')
            ->selectRaw('COUNT(*) as device_count')
            ->groupBy('device_fingerprint')
            ->havingRaw('COUNT(DISTINCT user_id) > 1')
            ->orderByDesc('user_count')
            ->paginate(15);
    }

    /**
     * @param  Collection<int, object{device_fingerprint: string}>  $groups
     * @return Collection<string, Collection<int, UserDevice>>
     */
    protected function devicesFor(Collection $groups): Collection
    {
        return UserDevice::whereIn('device_fingerprint', $groups->pluck('device_fingerprint'))
            ->with('user:id,name,email')
            ->latest('last_seen_at')
            ->get()
            ->groupBy('device_fingerprint');
    }

    public function render(): View
    {
        $groups = $this->groups();

        return view('livewire.admin.management.devices.shared-fingerprints', [
            'groups' => $groups,
            'devicesByFingerprint' => $this->devicesFor(collect($groups->items())),
        ])->title(__('devices.shared.title'));
    }
}
