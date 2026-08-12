<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Concerns;

/**
 * Shared by every FormRequest whose validation branches on whether the
 * caller is a browser (LoginRequest, SocialLoginRequest) — a browser can't
 * produce its own stable device fingerprint, so `device.*` isn't expected
 * from it (see BrowserDeviceResolver's docblock).
 */
trait DetectsBrowserClient
{
    public const CLIENT_TYPE_HEADER = 'X-Client-Type';

    public const WEB_CLIENT_TYPE = 'web';

    /**
     * Read directly off the header (not $this->validated(), which isn't
     * available yet while rules() itself is being built).
     */
    public function isBrowserClient(): bool
    {
        return $this->header(self::CLIENT_TYPE_HEADER) === self::WEB_CLIENT_TYPE;
    }
}
