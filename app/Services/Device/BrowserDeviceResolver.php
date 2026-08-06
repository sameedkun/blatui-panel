<?php

namespace App\Services\Device;

use App\Enum\DeviceType;
use App\Support\DeviceData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use UAParser\Parser;

/**
 * Builds {@see DeviceData} for a browser-based login/signup — the counterpart
 * to the client-supplied `device.*` payload a native app sends. A browser
 * can't produce a stable hardware fingerprint (privacy sandboxing, ITP, ad
 * blockers all fight that on purpose), so instead of asking it to, the
 * fingerprint is server-issued: reused from the `device_fp` cookie if the
 * browser already carries one, or freshly generated otherwise. The caller
 * (AuthController) is responsible for writing that value back onto the
 * response as a cookie so the *next* login from the same browser reuses the
 * same device row instead of registering a new one every time.
 *
 * Name/platform/os are parsed from the User-Agent purely for display in the
 * Devices admin UI ("Chrome on Windows") — never used for anything
 * security-sensitive, since a User-Agent string is trivially spoofable.
 */
class BrowserDeviceResolver
{
    public const COOKIE_NAME = 'device_fp';

    public function resolve(Request $request): DeviceData
    {
        $client = Parser::create()->parse($request->userAgent() ?? '');

        return new DeviceData(
            fingerprint: $request->cookie(self::COOKIE_NAME) ?? (string) Str::uuid(),
            name: "{$client->ua->family} on {$client->os->family}",
            model: $client->device->family !== 'Other' ? $client->device->family : null,
            platform: $client->os->family,
            os: trim("{$client->os->family} {$client->os->toVersion()}"),
            deviceType: DeviceType::Web,
            browser: $client->ua->family,
        );
    }
}
