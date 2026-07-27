<?php

namespace App\Livewire\Admin\Management\BlockedIps\Concerns;

use App\Models\UserDevice;
use Illuminate\Support\Collection;

/**
 * "Who is behind this IP" side panel — answers the question you actually
 * have before blocking, in place of a Show page a blocked_ips row doesn't
 * need. Gated behind `devices.view` since it surfaces user emails.
 */
trait HandlesIpActivityPanel
{
    public ?string $activityIp = null;

    public function openIpActivityPanel(string $ip): void
    {
        $this->authorize('devices.view');

        $this->activityIp = $ip;
        $this->dispatch('open-drawer-ip-activity');
    }

    /**
     * @return Collection<int, UserDevice>
     */
    public function ipActivityDevices(): Collection
    {
        if ($this->activityIp === null) {
            return collect();
        }

        return UserDevice::where('ip_address', $this->activityIp)
            ->with('user:id,name,email')
            ->latest('last_seen_at')
            ->limit(50)
            ->get();
    }

    public function ipActivityDistinctUserCount(): int
    {
        if ($this->activityIp === null) {
            return 0;
        }

        return UserDevice::where('ip_address', $this->activityIp)
            ->where('last_seen_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');
    }
}
