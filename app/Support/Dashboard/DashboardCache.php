<?php

namespace App\Support\Dashboard;

use App\Support\Dashboard\Blocks\Block;
use App\Support\Dashboard\Blocks\Row;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived caching for dashboard payloads.
 *
 * Cached per widget/section rather than per query: the unit the user sees
 * refresh should be one payload, never a dozen aggregates that can drift out
 * of step with each other. The key carries the locale because payloads embed
 * translated labels.
 *
 * Invalidation is a generation counter rather than a key list, so the
 * Refresh button can drop every dashboard payload on any cache store (tags
 * aren't available on file/database stores) without knowing what exists.
 *
 * Payloads are serialized here rather than by the cache store: the app keeps
 * `cache.serializable_classes` at `false` (no objects come back out of the
 * cache, against gadget chains if APP_KEY leaks), so the store would return
 * blocks as __PHP_Incomplete_Class. Instead this class unserializes with an
 * allow-list of exactly the dashboard value objects present — Row and Block
 * subclasses, including an application's own — and nothing else.
 */
class DashboardCache
{
    private const string GENERATION_KEY = 'dashboard:generation';

    public function remember(string $key, DateRange $range, Closure $callback): mixed
    {
        $ttl = (int) config('dashboard.cache_seconds', 300);

        if ($ttl <= 0) {
            return $callback();
        }

        $cacheKey = $this->key($key, $range);
        $payload = Cache::get($cacheKey);

        // Anything that isn't one of our serialized strings (a miss, or an entry
        // written some other way) is recomputed and replaced.
        if (! is_string($payload)) {
            $payload = serialize($callback());
            Cache::put($cacheKey, $payload, $ttl);
        }

        return $this->restore($payload);
    }

    /** Invalidate every cached dashboard payload at once. */
    public function flush(): void
    {
        Cache::forever(self::GENERATION_KEY, $this->generation() + 1);
    }

    /** Unserialize a payload, allowing only dashboard value objects to be instantiated. */
    private function restore(string $payload): mixed
    {
        preg_match_all('/O:\d+:"([^"]+)"/', $payload, $matches);

        $allowed = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $class): bool => $class === Row::class || is_a($class, Block::class, true),
        ));

        return unserialize($payload, ['allowed_classes' => $allowed]);
    }

    private function generation(): int
    {
        return (int) Cache::get(self::GENERATION_KEY, 0);
    }

    private function key(string $key, DateRange $range): string
    {
        return sprintf('dashboard:%d:%s:%s:%s', $this->generation(), $key, $range->cacheKey(), app()->getLocale());
    }
}
