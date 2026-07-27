<?php

namespace App\Models;

use App\Http\Middleware\CheckBlockedIp;
use Database\Factories\BlockedIpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A block on an IP address — global (`user_id` null) or scoped to a single
 * user. `user_scope` is a DB-generated column (`COALESCE(user_id, 0)`, see
 * the migration) that backs the unique constraint: one global block per IP,
 * one per-user block per (IP, user) pair. All mutations go through the
 * BlockedIps admin Livewire components — never written to directly.
 *
 * {@see CheckBlockedIp} caches its per-(ip, user) lookup
 * under a `blocked-ip:{ip}` tag. A single-row create/update/delete here
 * self-invalidates that tag via the hooks below, so a deleted/edited block
 * takes effect immediately instead of waiting out the cache TTL. Bulk
 * `whereIn(...)->delete()` paths (the Index's bulk-delete/delete-expired
 * actions, and the PruneExpiredBlockedIps job) bypass Eloquent events
 * entirely — those call {@see forgetCache()} explicitly.
 */
class BlockedIp extends Model
{
    /** @use HasFactory<BlockedIpFactory> */
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'user_id',
        'reason',
        'blocked_by',
        'hits',
        'last_hit_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_hit_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (BlockedIp $block) => static::forgetCache($block->ip_address));

        static::updated(function (BlockedIp $block) {
            if ($block->wasChanged('ip_address')) {
                static::forgetCache($block->getOriginal('ip_address'));
            }

            // user_id/expires_at changes affect whether this row still counts as a
            // block — hits/last_hit_at (recordHit()) don't, so skip those no-op flushes.
            if ($block->wasChanged(['ip_address', 'user_id', 'expires_at'])) {
                static::forgetCache($block->ip_address);
            }
        });

        static::deleted(fn (BlockedIp $block) => static::forgetCache($block->ip_address));
    }

    /**
     * Flushes every cached {@see CheckBlockedIp} lookup for one or
     * more IPs (across every user variant) in a single call per IP, regardless of how
     * many distinct (ip, user) cache keys exist for it.
     *
     * @param  string|iterable<string>  $ipAddresses
     */
    public static function forgetCache(string|iterable $ipAddresses): void
    {
        foreach (is_string($ipAddresses) ? [$ipAddresses] : $ipAddresses as $ip) {
            Cache::tags(["blocked-ip:{$ip}"])->flush();
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function recordHit(): void
    {
        $this->increment('hits', 1, ['last_hit_at' => now()]);
    }
}
