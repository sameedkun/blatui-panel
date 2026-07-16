<?php

namespace App\Support;

/**
 * Minimal, dependency-free "OS • Browser" label for the activity timeline.
 * Not a full device-detection library — just enough to render something
 * human-readable next to a login/failed-login entry.
 */
class UserAgentParser
{
    /** e.g. "Windows • Chrome", or null if nothing recognizable was found. */
    public static function device(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        $label = implode(' • ', array_filter([self::os($userAgent), self::browser($userAgent)]));

        return $label !== '' ? $label : null;
    }

    protected static function os(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    /** Order matters: Edge/Opera UAs also contain "Chrome/", and Chrome UAs also contain "Safari/". */
    protected static function browser(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            default => null,
        };
    }
}
