<?php

namespace App\Jobs;

use App\Services\DeviceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Thin monthly trigger for the device-retention sweep. All logic lives in
 * {@see DeviceService::pruneRevoked()} — this job just invokes it.
 */
class PruneRevokedDevices implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $months) {}

    /** Deletion is idempotent per device, so a failed sweep isn't retried — next month's run catches stragglers. */
    public int $tries = 1;

    public int $timeout = 300;

    public function handle(DeviceService $devices): void
    {
        $devices->pruneRevoked($this->months);
    }
}
