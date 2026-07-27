<?php

namespace App\Jobs;

use App\Models\UserDevice;
use App\Services\DeviceService;
use App\Services\LocationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resolves city/country/country_code for a device's IP via {@see LocationService}
 * and persists it. Kept off the request path — {@see DeviceService::register()}
 * / {@see DeviceService::touch()} dispatch this rather than calling the geo-IP
 * provider synchronously, since that's an external HTTP call on every
 * login/IP change.
 */
class ResolveDeviceLocation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(
        private readonly int $deviceId,
        private readonly string $ip,
    ) {}

    public function handle(LocationService $location, DeviceService $devices): void
    {
        $device = UserDevice::find($this->deviceId);

        // Nothing to do if the device is gone, or its IP has already moved on
        // since this job was queued (a newer job for the newer IP either has
        // already run or is already queued behind this one) — applying this
        // job's result now would clobber a fresher value with a stale one.
        if (! $device || $device->ip_address !== $this->ip) {
            return;
        }

        $devices->updateLocation($device, $location->getLocationFromIP($this->ip));
    }
}
