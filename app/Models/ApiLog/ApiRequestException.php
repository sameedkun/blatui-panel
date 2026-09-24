<?php

namespace App\Models\ApiLog;

use App\Models\User;
use Database\Factories\ApiLog\ApiRequestExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exception reported while serving an API request (30-day retention —
 * longer than the raw log it belongs to). Carries its own copy of the
 * request's method/route/status/user so it stays readable once the
 * {@see ApiRequestLog} row has been pruned. `fingerprint` (sha1 of
 * class|file|line) groups repeat occurrences of the same failure.
 */
class ApiRequestException extends Model
{
    /** @use HasFactory<ApiRequestExceptionFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'status_code' => 'integer',
            'trace' => 'array',
            'previous' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApiRequestLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ApiRequestLog::class, 'request_id', 'request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
