<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'native_name',
        'code',
        'flag',
        'is_rtl',
        'is_default',
        'is_active',
        'sort_order',
        'translations',
    ];

    protected function casts(): array
    {
        return [
            'is_rtl' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'translations' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Renders the `flag` country code (e.g. "us") as a flag emoji, via Unicode regional indicators. */
    public function flagEmoji(): ?string
    {
        if (! $this->flag || strlen($this->flag) !== 2) {
            return null;
        }

        return collect(str_split(strtoupper($this->flag)))
            ->map(fn (string $letter): string => mb_chr(127397 + ord($letter)))
            ->implode('');
    }
}
