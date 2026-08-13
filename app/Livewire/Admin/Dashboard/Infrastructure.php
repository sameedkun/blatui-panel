<?php

namespace App\Livewire\Admin\Dashboard;

use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * White-label Target Infrastructure Console.
 *
 * Designed specifically as a reusable template console for external target infrastructure
 * (VPN node fleets, regional egress proxies, edge clusters, or AI inference workers).
 */
#[Lazy]
class Infrastructure extends Component
{
    public string $selectedRange;

    public function render(): View
    {
        return view('livewire.admin.dashboard.infrastructure', [
            'fleetStats' => [
                'total_nodes' => 24,
                'active_nodes' => 22,
                'maintenance_nodes' => 2,
                'regional_clusters' => 6,
                'egress_bandwidth' => '1.42 Gbps',
                'average_latency' => '24 ms',
                'fleet_utilization' => 68,
            ],
            'regionalClusters' => [
                ['name' => 'US-East (N. Virginia)', 'nodes' => 8, 'latency' => '18 ms', 'status' => 'online', 'load' => 74],
                ['name' => 'EU-Central (Frankfurt)', 'nodes' => 6, 'latency' => '28 ms', 'status' => 'online', 'load' => 62],
                ['name' => 'AP-East (Tokyo)', 'nodes' => 4, 'latency' => '42 ms', 'status' => 'online', 'load' => 58],
                ['name' => 'SA-East (São Paulo)', 'nodes' => 4, 'latency' => '65 ms', 'status' => 'maintenance', 'load' => 80],
                ['name' => 'AF-South (Cape Town)', 'nodes' => 2, 'latency' => '88 ms', 'status' => 'standby', 'load' => 25],
            ],
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.admin.dashboard.placeholder');
    }
}
