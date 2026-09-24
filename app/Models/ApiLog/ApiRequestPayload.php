<?php

namespace App\Models\ApiLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The heavy, already-sanitized half of an {@see ApiRequestLog} — headers,
 * query, bodies, slow queries. Optional (0..1 per request): only written when
 * the request carried something worth keeping, see config('api_logs.capture').
 *
 * A body over config('api_logs.max_body_kb') is stored as a truncated JSON
 * *string* (with the matching *_truncated flag set) rather than a decoded
 * structure, since a cut-off document is no longer valid JSON.
 */
class ApiRequestPayload extends Model
{
    const UPDATED_AT = null;

    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'query' => 'array',
            'request_body' => 'json',
            'response_headers' => 'array',
            'response_body' => 'json',
            'slow_queries' => 'array',
            'request_truncated' => 'boolean',
            'response_truncated' => 'boolean',
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
}
