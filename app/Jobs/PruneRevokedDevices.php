<?php

namespace App\Jobs;

use App\Services\Device\DeviceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin monthly trigger for the device-retention sweep. All logic lives in
 * {@see DeviceService::pruneRevoked()} — this job just invokes it.
 */
class PruneRevokedDevices implements ShouldQueue
{
    use Queueable;

    /** Deletion is idempotent per device, so a failed sweep isn't retried — next month's run catches stragglers. */
    public int $tries = 1;

    public int $timeout = 300;

    public function handle(DeviceService $devices): void
    {
        $devices->pruneRevoked(config('panel.user_device_revoked_retention_months'));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PruneRevokedDevices', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
