<?php

namespace App\Http\Middleware;

use App\Models\UserDevice;
use App\Services\DeviceService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the authenticated API group. Resolves the device that issued the
 * current request's token and rejects with a machine-readable error code if
 * it's missing, revoked, or blocked, so the client app can show the right
 * screen and clear its local credentials. Otherwise refreshes the device's
 * last-seen state via {@see DeviceService::touch()}.
 */
class EnsureDeviceIsValid
{
    public function __construct(private readonly DeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $device = $token instanceof PersonalAccessToken
            ? UserDevice::where('token_id', $token->id)->first()
            : null;

        if ($device?->is_blocked) {
            return response()->json([
                'status' => false,
                'error' => 'DEVICE_BLOCKED',
                'message' => 'This device has been blocked.',
            ], 401);
        }

        if (! $device || $device->is_revoked) {
            return response()->json([
                'status' => false,
                'error' => 'DEVICE_REVOKED',
                'message' => 'This device is no longer valid. Please log in again.',
            ], 401);
        }

        $this->devices->touch($device, $request->ip());

        return $next($request);
    }
}
