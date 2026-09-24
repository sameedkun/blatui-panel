<?php

namespace App\Models\ApiLog;

use App\Models\User;
use App\Support\ApiLogs\ApiLogWriter;
use Database\Factories\ApiLog\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One raw API request (7-day retention). Written in bulk by
 * {@see ApiLogWriter}, never through Eloquent on the hot
 * path. `request_id` is the public handle and the key every related table
 * uses — the numeric id is internal only.
 */
class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory;

    /** Stored as route_uri when a request matched no route (e.g. a typo'd endpoint). */
    public const string UNMATCHED_ROUTE = '<unmatched>';

    const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'status_class' => 'integer',
            'duration_ms' => 'float',
            'db_time_ms' => 'float',
            'has_payload' => 'boolean',
            'has_exception' => 'boolean',
            'timings' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** "1.4 KB"-style display size (no intl dependency, unlike Number::fileSize()). */
    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    public function getRouteKeyName(): string
    {
        return 'request_id';
    }

    /**
     * @return HasOne<ApiRequestPayload, $this>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(ApiRequestPayload::class, 'request_id', 'request_id');
    }

    /**
     * @return HasMany<ApiRequestException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ApiRequestException::class, 'request_id', 'request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeCorrelatedWith(Builder $query, string $correlationId): Builder
    {
        return $query->where('correlation_id', $correlationId);
    }
}
