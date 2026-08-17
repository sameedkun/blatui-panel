<?php

namespace App\Support;

use App\Http\Middleware\CheckBlockedIp;
use App\Models\BlockedIp;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Shared cache access for {@see CheckBlockedIp} and
 * {@see BlockedIp}'s invalidation hooks, so both call sites
 * degrade the same way when the configured cache store doesn't support
 * tagging — Laravel only ships tag support on memcached/redis/dynamodb/array
 * (`Illuminate\Cache\TaggableStore`); `database`/`file`/`apc` throw a
 * `BadMethodCallException` from `Cache::tags()`. `config('cache.default')`
 * falls back to `database` when unset, and that's exactly what's deployed on
 * hosts without Redis available — so this can't assume tagging works.
 *
 * On an unsupported store, lookups still cache (a plain key, no tag), but
 * `forget()` becomes a no-op — an edited/deleted block on such a store takes
 * up to the caller's TTL to take effect instead of invalidating immediately.
 * That's an acceptable degradation: the block is still enforced, just not
 * instantly refreshed.
 */
class BlockedIpCache
{
    public static function remember(string $ip, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        return static::repository($ip)->remember($key, $ttlSeconds, $callback);
    }

    /**
     * @param  string|iterable<string>  $ipAddresses
     */
    public static function forget(string|iterable $ipAddresses): void
    {
        if (! static::supportsTags()) {
            return;
        }

        foreach (is_string($ipAddresses) ? [$ipAddresses] : $ipAddresses as $ip) {
            Cache::tags([static::tag($ip)])->flush();
        }
    }

    private static function repository(string $ip): Repository
    {
        return static::supportsTags() ? Cache::tags([static::tag($ip)]) : Cache::store();
    }

    private static function tag(string $ip): string
    {
        return "blocked-ip:{$ip}";
    }

    private static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
