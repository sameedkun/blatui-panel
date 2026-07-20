<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton SMTP connection + From identity, used only when
 * `config('mail.default') === 'smtp'`. Access via {@see self::current()}
 * rather than querying the table directly.
 */
#[Fillable([
    'host',
    'port',
    'encryption',
    'username',
    'password',
    'from_address',
    'from_name',
])]
class SmtpSetting extends Model
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /** The single settings row, or an unsaved instance if never configured. */
    public static function current(): self
    {
        return static::query()->first() ?? new self;
    }
}
