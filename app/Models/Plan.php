<?php

namespace App\Models;

use App\Traits\HasFeatures;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\Attributes\Sluggable;

#[Sluggable(from: 'name', to: 'slug')]
#[Fillable([
    'name',
    'description',
    'features',
    'is_active',
    'is_best_deal',
    'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasFeatures;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'is_best_deal' => 'boolean',
        ];
    }

    /**
     * Get all prices for this plan.
     *
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * Get all subscriptions on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
