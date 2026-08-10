<?php

namespace App\Http\Resources\V1;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'price' => new PlanPriceResource($this->whenLoaded('planPrice')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->isActive(),
            'is_recurring' => $this->is_recurring,
            'is_on_trial' => $this->isOnTrial(),
            'is_in_grace' => $this->isInGrace(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'grace_ends_at' => $this->grace_ends_at?->toIso8601String(),
            'amount_paid' => $this->amount_paid,
            'currency' => $this->currency,
            'provider' => $this->provider->value,
            'cancelled_by' => $this->cancelled_by?->value,
            'cancelled_reason' => $this->cancelled_reason,
        ];
    }
}
