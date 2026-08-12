<?php

namespace App\Support\Dashboard;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The dashboard's single entry point to every metric domain.
 *
 * Widgets never construct a metrics class themselves — they reach it through here, so
 * adding a domain later (VPN nodes, inference usage, …) means adding one property here
 * and one widget entry, with nothing else to rewire.
 *
 * Caching lives at this level rather than inside the individual metric methods: a widget
 * is the unit the user actually sees refresh, and a single cached payload per widget beats
 * a dozen separately-cached aggregates that can drift out of step with each other.
 */
class DashboardMetrics
{
    public function __construct(
        public readonly AudienceMetrics $audience,
        public readonly RevenueMetrics $revenue,
        public readonly SupportMetrics $support,
        public readonly SecurityMetrics $security,
        public readonly SystemMetrics $system,
    ) {}

    /**
     * Cache a widget's resolved payload for the configured TTL.
     *
     * The key carries the active locale because most payloads embed translated axis
     * labels — without it, switching language would keep serving the previous locale's
     * strings until the entry expired.
     */
    public function remember(string $key, DateRange $range, Closure $callback): mixed
    {
        $ttl = (int) config('panel.dashboard_cache_seconds', 300);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember(
            self::cacheKey($key, $range),
            $ttl,
            $callback,
        );
    }

    /** Drop every cached widget payload for one range — backs the page's Refresh action. */
    public function forget(DateRange $range, array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget(self::cacheKey($key, $range));
        }
    }

    private static function cacheKey(string $key, DateRange $range): string
    {
        return sprintf('dashboard:%s:%s:%s', $key, $range->value, app()->getLocale());
    }
}
