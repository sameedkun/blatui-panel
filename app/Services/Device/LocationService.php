<?php

namespace App\Services\Device;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves a human-readable location from an IP address. ipinfo.io is the
 * primary provider (more reliable, HTTPS) with ip-api.com as a free fallback
 * (no token required) when ipinfo fails or isn't configured. Both providers'
 * responses are normalized to the same shape so callers never need to know
 * which one actually answered.
 */
class LocationService
{
    /** Geo-IP mappings for a given address barely ever change — cache generously to protect provider rate limits. */
    private const CACHE_TTL_DAYS = 7;

    /**
     * @return array{city: ?string, country: ?string, countryCode: ?string, lat: ?float, lon: ?float, formatted: string, provider: ?string}
     */
    public function getLocationFromIP(string $ip, ?string $provider = null): array
    {
        if ($this->isLocalIP($ip)) {
            return [
                'city' => 'Localhost',
                'country' => null,
                'countryCode' => null,
                'lat' => null,
                'lon' => null,
                'formatted' => 'Localhost',
                'provider' => null,
            ];
        }

        $cacheKey = $provider ? "ip_location_{$ip}_{$provider}" : "ip_location_{$ip}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($ip, $provider) {
            return match ($provider) {
                'ipinfo' => $this->getFromIpInfo($ip) ?? $this->nullLocation(),
                'ip-api' => $this->getFromIpApi($ip) ?? $this->nullLocation(),
                // ipinfo first — more reliable/accurate than the free ip-api tier — falling
                // back to ip-api when ipinfo has no token configured or its request fails.
                default => $this->getFromIpInfo($ip) ?? $this->getFromIpApi($ip) ?? $this->nullLocation(),
            };
        });
    }

    /**
     * @return array{city: null, country: null, countryCode: null, lat: null, lon: null, formatted: string, provider: null}
     */
    protected function nullLocation(): array
    {
        return [
            'city' => null,
            'country' => null,
            'countryCode' => null,
            'lat' => null,
            'lon' => null,
            'formatted' => 'Unknown',
            'provider' => null,
        ];
    }

    /**
     * @return array{city: ?string, country: ?string, countryCode: ?string, lat: ?float, lon: ?float, formatted: string, provider: string}|null
     */
    protected function getFromIpApi(string $ip): ?array
    {
        try {
            $res = Http::timeout(4)->get("http://ip-api.com/json/{$ip}");

            if ($res->failed() || $res->json('status') !== 'success') {
                return null;
            }

            $data = $res->json();

            return $this->normalize(
                city: $data['city'] ?? null,
                country: $data['country'] ?? null,
                countryCode: $data['countryCode'] ?? null,
                lat: $data['lat'] ?? null,
                lon: $data['lon'] ?? null,
                provider: 'ip-api',
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * ipinfo's free tier returns `country` as the ISO-3166 alpha-2 code, not a
     * full name (unlike ip-api) — `countryCode` mirrors it since no separate
     * full-name field exists to fill `country` with here.
     *
     * @return array{city: ?string, country: ?string, countryCode: ?string, lat: ?float, lon: ?float, formatted: string, provider: string}|null
     */
    protected function getFromIpInfo(string $ip): ?array
    {
        $token = config('services.ipinfo.token');

        if (! $token) {
            return null;
        }

        try {
            $res = Http::timeout(4)->get("https://ipinfo.io/{$ip}/json", ['token' => $token]);

            if ($res->failed() || $res->json('bogon') === true) {
                return null;
            }

            $data = $res->json();
            [$lat, $lon] = $this->parseLoc($data['loc'] ?? null);

            return $this->normalize(
                city: $data['city'] ?? null,
                country: $data['country'] ?? null,
                countryCode: $data['country'] ?? null,
                lat: $lat,
                lon: $lon,
                provider: 'ipinfo',
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{city: ?string, country: ?string, countryCode: ?string, lat: ?float, lon: ?float, formatted: string, provider: string}
     */
    protected function normalize(?string $city, ?string $country, ?string $countryCode, mixed $lat, mixed $lon, string $provider): array
    {
        return [
            'city' => $city,
            'country' => $country,
            'countryCode' => $countryCode,
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lon' => is_numeric($lon) ? (float) $lon : null,
            'formatted' => collect([$city, $country])->filter()->implode(', ') ?: 'Unknown',
            'provider' => $provider,
        ];
    }

    /** ipinfo packs coordinates as a single "lat,lon" string. */
    protected function parseLoc(?string $loc): array
    {
        if (! $loc || ! str_contains($loc, ',')) {
            return [null, null];
        }

        [$lat, $lon] = array_pad(explode(',', $loc, 2), 2, null);

        return [$lat, $lon];
    }

    protected function isLocalIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
