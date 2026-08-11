<?php

namespace App\Http\Resources\V1;

use App\Models\PlanPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanPrice
 */
class PlanPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'compare_at_amount' => $this->compare_at_amount,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'billing_interval' => $this->billing_interval->value,
            'providers' => PlanPriceProviderResource::collection($this->whenLoaded('providers')),
        ];
    }
}
