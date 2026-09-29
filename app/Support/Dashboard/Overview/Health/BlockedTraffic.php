<?php

namespace App\Support\Dashboard\Overview\Health;

use App\Models\BlockedIp;
use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Overview\OverviewWidget;

/**
 * Active IP blocks. Global blocks are flagged: one can lock out every user
 * behind a carrier NAT at once, so they deserve a second look.
 */
class BlockedTraffic extends OverviewWidget
{
    protected ?string $permission = 'blocked-ips.view';

    public function build(DateRange $range): HealthIndicator
    {
        $active = BlockedIp::query()->active()->count();
        $global = BlockedIp::query()->active()->global()->count();

        return HealthIndicator::make(
            __('dashboard.health.blocked_ips'),
            number_format($active),
            $global > 0 ? HealthIndicator::WARNING : HealthIndicator::OK,
            'shield-ban',
        )
            ->hint(__('dashboard.health.global_blocks', ['count' => number_format($global)]))
            ->link(route('admin.blocked-ips.index'), 'blocked-ips.view');
    }
}
