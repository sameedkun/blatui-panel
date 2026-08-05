<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Whether a request targets the versioned API surface — used to gate JSON
 * error rendering (bootstrap/app.php, ApiExceptionRenderer) without
 * hardcoding an "api/*" URL prefix. grazulex/laravel-apiroute's uri
 * strategy (config/apiroute.php's strategies.uri) can later move to a
 * domain-based scheme — e.g. api.example.com serving bare "/v1/..." paths
 * with prefix set to '' — so this reads the strategy config dynamically
 * instead of assuming the current "api/v1/..." shape.
 */
final class ApiRequest
{
    public static function targets(Request $request): bool
    {
        // Set by laravel-apiroute's ResolveApiVersion middleware once a
        // request has matched a registered version, under any detection
        // strategy (uri, header, query, accept) — the strongest possible
        // signal, since it doesn't depend on the URL shape at all.
        if ($request->attributes->has('api_version')) {
            return true;
        }

        // Falls back to the uri strategy's own prefix/domain config for
        // requests that never matched a route at all (e.g. a typo'd
        // endpoint) — those never reach ResolveApiVersion, but should still
        // render as a JSON 404 rather than the app's default HTML page.
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);

        $domains = match (true) {
            is_string($uriConfig['domain'] ?? null) => [$uriConfig['domain']],
            is_array($uriConfig['domain'] ?? null) => $uriConfig['domain'],
            default => [],
        };

        if ($domains !== []) {
            foreach ($domains as $domain) {
                if (Str::is($domain, $request->getHost())) {
                    return true;
                }
            }

            return false;
        }

        $prefix = $uriConfig['prefix'] ?? 'api';

        return $prefix === '' || $request->is($prefix.'/*');
    }
}
