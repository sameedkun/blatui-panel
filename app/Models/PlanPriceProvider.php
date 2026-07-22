<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use Database\Factories\PlanPriceProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_price_id',
    'provider',
    'external_id',
    'is_active',
])]
class PlanPriceProvider extends Model
{
    /** @use HasFactory<PlanPriceProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the plan price this provider mapping belongs to.
     *
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }
}
