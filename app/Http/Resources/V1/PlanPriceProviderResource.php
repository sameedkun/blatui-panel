<?php

namespace App\Http\Resources\V1;

use App\Models\PlanPriceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanPriceProvider
 */
class PlanPriceProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider->value,
            'external_id' => $this->external_id,
        ];
    }
}
