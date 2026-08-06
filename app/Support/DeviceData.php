<?php

namespace App\Support;

use App\Enum\DeviceType;
use App\Jobs\Device\ResolveDeviceLocation;
use App\Services\Device\BrowserDeviceResolver;
use App\Services\Device\DeviceService;

/**
 * Device payload passed into {@see DeviceService::register()} — either the
 * client-supplied `device.*` request payload (native apps) or built
 * server-side by {@see BrowserDeviceResolver} from the
 * User-Agent + a server-issued cookie (browsers, which can't produce a
 * stable fingerprint themselves). `fingerprint` is always the raw value
 * either way — the service hashes it before it ever touches the database
 * (see design decision: fingerprints are stored hashed, never in plaintext).
 * Deliberately carries no location fields — city/country/country_code are
 * always resolved server-side from the request IP (see
 * {@see ResolveDeviceLocation}), never trusted from the client.
 */
final readonly class DeviceData
{
    public function __construct(
        public string $fingerprint,
        public ?string $name = null,
        public ?string $model = null,
        public ?string $platform = null,
        public ?string $os = null,
        public ?DeviceType $deviceType = null,
        public ?string $appVersion = null,
        public ?string $browser = null,
        public ?string $pushToken = null,
        public ?string $pushProvider = null,
    ) {}
}
