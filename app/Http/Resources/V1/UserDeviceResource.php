<?php

namespace App\Http\Resources\V1;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class UserDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'model' => $this->model,
            'platform' => $this->platform,
            'os' => $this->os,
            'app_version' => $this->app_version,
            'ip_address' => $this->ip_address,
            'city' => $this->city,
            'country' => $this->country,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'status' => match (true) {
                $this->is_blocked => 'blocked',
                $this->is_revoked => 'revoked',
                default => 'active',
            },
            // Whether this row is the device that authenticated the current
            // request — lets the client highlight "this device" in the list.
            'current' => $this->token_id !== null
                && $this->token_id === $request->user()?->currentAccessToken()?->id,
        ];
    }
}
