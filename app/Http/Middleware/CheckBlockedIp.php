<?php

namespace App\Http\Middleware;

use App\Jobs\Auth\RecordBlockedIpHit;
use App\Models\BlockedIp;
use App\Support\BlockedIpCache;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before auth on every API route (prepended to the global 'api'
 * middleware group — see bootstrap/app.php). auth:sanctum hasn't executed yet
 * at this point, so $request->user() isn't resolved — the acting user is
 * looked up directly off the bearer token instead, so a per-user block can
 * still be checked this early. Result is cached briefly since this runs on
 * every request (keyed per (ip, user), tagged `blocked-ip:{ip}` — via
 * {@see BlockedIpCache}, which also handles cache stores that don't support
 * tagging — so {@see BlockedIp}'s model hooks can invalidate every cached
 * user variant for an IP in one call the moment a block is
 * created/edited/deleted, rather than waiting out the TTL); the hit counter
 * itself is incremented off the request path.
 */
class CheckBlockedIp
{
    private const CACHE_TTL_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userId = $this->resolveUserId($request);
        $cacheKey = "blocked-ip:{$ip}:".($userId ?? 'guest');

        $blocked = BlockedIpCache::remember($ip, $cacheKey, self::CACHE_TTL_SECONDS, fn (): bool => BlockedIp::query()
            ->where('ip_address', $ip)
            ->where(fn ($q) => $q->whereNull('user_id')->when($userId, fn ($q2) => $q2->orWhere('user_id', $userId)))
            ->active()
            ->exists());

        if ($blocked) {
            RecordBlockedIpHit::dispatch($ip, $userId);

            return response()->json([
                'status' => false,
                'message' => 'Access from this IP address has been blocked.',
                'error' => 'IP_BLOCKED',
            ], 403);
        }

        return $next($request);
    }

    private function resolveUserId(Request $request): ?int
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        return PersonalAccessToken::findToken($token)?->tokenable_id;
    }
}
