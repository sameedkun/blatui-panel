<?php

namespace Database\Seeders;

use App\Enum\DeviceType;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Local demo records for exercising the Devices and Blocked IPs admin tools.
 *
 * It is safe to run repeatedly: the named fingerprints and IP/scope pairs are
 * updated in place, and active devices retain their existing token.
 */
class DeviceManagementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->appUsers()->orderBy('id')->limit(10)->get();

        if ($users->isEmpty()) {
            return;
        }

        $operator = User::query()->staff()->orderBy('id')->first();

        $devices = [
            ['Office MacBook Pro', 'MacBook Pro 14', 'web', 'macOS 15.4', DeviceType::Desktop, '2.4.1', '198.51.100.10', 'Lahore', 'Pakistan', 'PK', 'active', 'shared-household-browser'],
            ['iPhone 15 Pro', 'iPhone 15 Pro', 'ios', 'iOS 18.4', DeviceType::Mobile, '2.4.1', '203.0.113.20', 'Karachi', 'Pakistan', 'PK', 'active', 'shared-household-browser'],
            ['Pixel 9', 'Pixel 9', 'android', 'Android 15', DeviceType::Mobile, '2.3.8', '203.0.113.31', 'Islamabad', 'Pakistan', 'PK', 'active', 'pixel-9'],
            ['Safari on iPad', 'iPad Air', 'web', 'iPadOS 18.3', DeviceType::Tablet, '2.4.0', '198.51.100.44', 'Dubai', 'United Arab Emirates', 'AE', 'active', 'ipad-air'],
            ['Windows workstation', 'Surface Laptop 7', 'web', 'Windows 11', DeviceType::Desktop, '2.2.5', '192.0.2.17', 'London', 'United Kingdom', 'GB', 'active', 'surface-laptop'],
            ['Old Android phone', 'Galaxy S22', 'android', 'Android 14', DeviceType::Mobile, '2.1.9', '198.51.100.73', 'Faisalabad', 'Pakistan', 'PK', 'revoked', 'galaxy-s22-retired'],
            ['Retired browser', 'Dell XPS 13', 'web', 'Windows 10', DeviceType::Desktop, '2.0.4', '192.0.2.55', 'Multan', 'Pakistan', 'PK', 'revoked', 'dell-xps-retired'],
            ['Blocked iPhone', 'iPhone 13', 'ios', 'iOS 17.7', DeviceType::Mobile, '2.3.2', '203.0.113.88', 'Rawalpindi', 'Pakistan', 'PK', 'blocked', 'iphone-security-block'],
            ['Blocked Chrome browser', 'ThinkPad X1 Carbon', 'web', 'Windows 11', DeviceType::Desktop, '2.4.0', '198.51.100.91', 'Peshawar', 'Pakistan', 'PK', 'blocked', 'thinkpad-security-block'],
            ['Web fallback', 'Chrome on Linux', 'web', 'Ubuntu 24.04', DeviceType::Web, '2.4.1', '192.0.2.97', 'Toronto', 'Canada', 'CA', 'active', 'linux-chrome'],
        ];

        foreach ($devices as $index => [$name, $model, $platform, $os, $type, $version, $ip, $city, $country, $countryCode, $status, $fingerprint]) {
            $this->upsertDevice($users[$index], compact(
                'name', 'model', 'platform', 'os', 'type', 'version', 'ip', 'city', 'country', 'countryCode', 'status', 'fingerprint',
            ));
        }

        $this->seedBlockedIps($users, $operator);
    }

    /**
     * @param  array{name: string, model: string, platform: string, os: string, type: DeviceType, version: string, ip: string, city: string, country: string, countryCode: string, status: string, fingerprint: string}  $data
     */
    private function upsertDevice(User $user, array $data): void
    {
        $hash = hash('sha256', $data['fingerprint']);
        $device = UserDevice::query()->firstOrNew([
            'user_id' => $user->id,
            'device_fingerprint' => $hash,
        ]);

        $device->forceFill([
            'ulid' => $device->ulid ?: (string) Str::ulid(),
        ])->fill([
            'name' => $data['name'],
            'model' => $data['model'],
            'platform' => $data['platform'],
            'os' => $data['os'],
            'device_type' => $data['type'],
            'app_version' => $data['version'],
            'browser' => $data['platform'] === 'web' ? 'Chrome 137' : null,
            'ip_address' => $data['ip'],
            'city' => $data['city'],
            'country' => $data['country'],
            'country_code' => $data['countryCode'],
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 1_440)),
            'revoked_at' => $data['status'] === 'revoked' ? now()->subDays(3) : null,
            'blocked_at' => $data['status'] === 'blocked' ? now()->subHours(8) : null,
            'blocked_reason' => $data['status'] === 'blocked' ? 'Seeded for testing the blocked-device workflow.' : null,
        ]);

        if ($data['status'] === 'active') {
            $device->token_id ??= $this->createToken($user)->id;
        } else {
            $device->token_id = null;
        }

        $device->save();
    }

    private function createToken(User $user): PersonalAccessToken
    {
        return $user->createToken('seeded device', ['*'])->accessToken;
    }

    private function seedBlockedIps(Collection $users, ?User $operator): void
    {
        $blocks = [
            ['203.0.113.200', null, 'Global test block with recent traffic.', 86, now()->subMinutes(9), now()->addDays(5)],
            ['198.51.100.128', null, 'Permanent global block for admin workflow testing.', 22, now()->subHours(4), null],
            ['192.0.2.101', $users[0]->id, 'Per-user test block.', 14, now()->subHour(), now()->addDays(3)],
            ['192.0.2.102', $users[1]->id, 'Expired per-user block retained for pruning tests.', 4, now()->subDays(8), now()->subDay()],
            ['203.0.113.201', $users[2]->id, 'Recently created per-user test block.', 0, null, now()->addHours(18)],
        ];

        foreach ($blocks as [$ipAddress, $userId, $reason, $hits, $lastHitAt, $expiresAt]) {
            BlockedIp::query()->updateOrCreate(
                ['ip_address' => $ipAddress, 'user_id' => $userId],
                compact('reason', 'hits') + [
                    'blocked_by' => $operator?->id,
                    'last_hit_at' => $lastHitAt,
                    'expires_at' => $expiresAt,
                ],
            );
        }
    }
}
