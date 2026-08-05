<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\V1\UserDeviceResource;
use App\Services\Device\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service device management — a user managing their own devices, e.g.
 * "sign out this device" from account settings. Scoped strictly to
 * $request->user()->devices(): a ulid for another account's device 404s
 * (via firstOrFail(), never a manual 403) rather than confirming it exists.
 * Blocking a device stays admin-only (see the admin panel's Devices module);
 * there is no self-service equivalent.
 */
class DeviceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->success(
            UserDeviceResource::collection(
                $request->user()->devices()->latest('last_seen_at')->get(),
            ),
        );
    }

    public function destroy(Request $request, string $ulid, DeviceService $devices): JsonResponse
    {
        $device = $request->user()->devices()->where('ulid', $ulid)->firstOrFail();

        $devices->revoke($device);

        return $this->success(message: 'Device signed out.');
    }
}
