<?php

namespace App\Models;

use Database\Factories\EmailDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A verified Resend sending domain. Freely added/edited/deleted by the admin;
 * {@see EmailSender} rows point at one of these to resolve a from-address.
 */
#[Fillable([
    'name',
    'domain',
    'description',
    'is_default',
    'is_active',
])]
class EmailDomain extends Model
{
    /** @use HasFactory<EmailDomainFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EmailSender, $this> */
    public function senders(): HasMany
    {
        return $this->hasMany(EmailSender::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
