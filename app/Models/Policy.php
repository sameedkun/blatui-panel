<?php

namespace App\Models;

use Database\Factories\PolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'key',
    'title',
])]
class Policy extends Model
{
    /** @use HasFactory<PolicyFactory> */
    use HasFactory;

    /**
     * Get all versions of this policy.
     *
     * @return HasMany<PolicyVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class);
    }

    /**
     * Get the active version of this policy.
     *
     * @return HasOne<PolicyVersion, $this>
     */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(PolicyVersion::class)->where('is_active', true);
    }
}
