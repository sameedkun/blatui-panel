<?php

namespace App\Services\Device;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\DeviceType;
use App\Exceptions\DeviceBlockedException;
use App\Exceptions\DeviceLimitExceededException;
use App\Jobs\Device\ResolveDeviceLocation;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ActivityLogger;
use App\Support\DeviceData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The only place device rows are mutated — controllers/Livewire components
 * call in here, never write to {@see UserDevice} directly. `register()` (and
 * `touch()`) deliberately never write an audit-log entry: this app's design
 * keeps no login history (one token per device, no session log), so treating
 * every login/heartbeat as an auditable event would recreate exactly that.
 * Only the admin-facing state transitions (revoke/block/unblock) are audited,
 * per the spec's explicit "every block, unblock, revoke... writes an audit
 * entry" requirement.
 */
class DeviceService
{
    /** Re-login is a no-op past this staleness — avoids a write on every API request. */
    private const TOUCH_THROTTLE_MINUTES = 5;

    /**
     * Register (or re-attach) a device for $user against the token that was
     * just issued. Reactivating a revoked device is subject to the same
     * device-limit check as a brand-new device; re-login on an already-active
     * device never is. A blocked device can never be reactivated this way.
     *
     * $ip is the request IP resolved server-side by the caller (never trusted
     * from the client) — recorded on the device immediately and used to queue
     * a location lookup once the transaction below has actually committed.
     *
     * @throws DeviceBlockedException
     * @throws DeviceLimitExceededException
     */
    public function register(User $user, DeviceData $data, PersonalAccessToken $token, string $ip): UserDevice
    {
        $hashedFingerprint = hash('sha256', $data->fingerprint);

        $device = DB::transaction(function () use ($user, $data, $token, $ip, $hashedFingerprint): UserDevice {
            // Locking the matched device row alone can't stop two concurrent
            // registrations of two *different* fingerprints from both passing
            // the limit check below — lock the user row itself so concurrent
            // logins for this user are serialized.
            User::whereKey($user->id)->lockForUpdate()->first();

            $existing = UserDevice::where('user_id', $user->id)
                ->where('device_fingerprint', $hashedFingerprint)
                ->lockForUpdate()
                ->first();

            if ($existing?->is_blocked) {
                throw new DeviceBlockedException('This device has been blocked and cannot log in again.');
            }

            if (! $existing || ! $existing->is_active) {
                $this->enforceLimit($user, $data->deviceType);
            }

            $attributes = $this->attributesFrom($data, $token, $ip);

            try {
                return UserDevice::updateOrCreate(
                    ['user_id' => $user->id, 'device_fingerprint' => $hashedFingerprint],
                    $attributes,
                );
            } catch (QueryException $e) {
                // Race on the (user_id, device_fingerprint) unique constraint —
                // another concurrent request just created the row. Retry the
                // lookup once and update that instead of 500ing.
                $device = UserDevice::where('user_id', $user->id)
                    ->where('device_fingerprint', $hashedFingerprint)
                    ->first();

                if (! $device) {
                    throw $e;
                }

                $device->forceFill($attributes)->save();

                return $device;
            }
        });

        // Dispatched after the transaction commits, not from inside it — a
        // queue worker could otherwise pick this up before the device row is
        // even visible outside the transaction.
        ResolveDeviceLocation::dispatch($device->id, $ip);

        return $device;
    }

    /**
     * $causer, if passed, is recorded as the causer instead of auth() — used
     * by {@see enforceLimit()} to attribute an auto-eviction to the logging-in
     * user rather than a null/system causer, since there's no ambient session
     * yet at that point in the login flow.
     */
    public function revoke(UserDevice $device, ?User $causer = null): void
    {
        $device->update(['revoked_at' => now()]);

        ActivityLogger::log(ActivityModule::Device, ActivityAction::Revoked, $device, [
            'device_name' => $device->name,
        ], causer: $causer !== null ? $causer : false);
    }

    /**
     * Revoke every active device for $user in one transaction. Returns how
     * many were revoked.
     */
    public function revokeAll(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $devices = UserDevice::where('user_id', $user->id)->active()->lockForUpdate()->get();

            if ($devices->isEmpty()) {
                return 0;
            }

            foreach ($devices as $device) {
                $device->update(['revoked_at' => now()]);
            }

            ActivityLogger::log(ActivityModule::Device, ActivityAction::Revoked, null, [
                'bulk' => true,
                'user_id' => $user->id,
                'device_ids' => $devices->pluck('id')->all(),
                'count' => $devices->count(),
            ]);

            return $devices->count();
        });
    }

    /**
     * $admin, if passed, is recorded as the causer instead of auth() — for
     * call sites without an ambient session (none exist for this yet, but
     * keeps the signature consistent with the rest of this service's peers).
     */
    public function block(UserDevice $device, string $reason, ?User $admin = null): void
    {
        $device->update([
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);

        ActivityLogger::log(ActivityModule::Device, ActivityAction::Blocked, $device, [
            'reason' => $reason,
            'device_name' => $device->name,
        ], causer: $admin !== null ? $admin : false);
    }

    /**
     * Lifts the block only — does NOT restore the token (already deleted when
     * the device was blocked). The user must log in again.
     */
    public function unblock(UserDevice $device): void
    {
        $device->update([
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);

        ActivityLogger::log(ActivityModule::Device, ActivityAction::Unblocked, $device, [
            'device_name' => $device->name,
        ]);
    }

    /**
     * Refresh last-seen/IP for an already-validated device. Throttled so an
     * app polling the API doesn't write on every single request. A location
     * lookup is only re-queued when the IP actually changed since the last
     * touch (e.g. mobile network roaming) — re-resolving on every throttled
     * heartbeat would just hammer the geo-IP provider for an unchanged value.
     */
    public function touch(UserDevice $device, string $ip): void
    {
        if ($device->last_seen_at?->gt(now()->subMinutes(self::TOUCH_THROTTLE_MINUTES))) {
            return;
        }

        $ipChanged = $device->ip_address !== $ip;

        $device->forceFill([
            'last_seen_at' => now(),
            'ip_address' => $ip,
        ])->save();

        if ($ipChanged) {
            ResolveDeviceLocation::dispatch($device->id, $ip);
        }
    }

    /** Applies a {@see LocationService::getLocationFromIP()} result to a device — called by {@see ResolveDeviceLocation}. */
    public function updateLocation(UserDevice $device, array $location): void
    {
        $device->forceFill([
            'city' => $location['city'] ?? null,
            'country' => $location['country'] ?? null,
            'country_code' => $location['countryCode'] ?? null,
        ])->save();
    }

    /**
     * Permanently deletes every revoked (never blocked) device older than
     * $months — data retention, called monthly by the scheduler. Blocked
     * devices are excluded: their `blocked_reason` is the only record of why
     * they were blocked, so they're never swept by this retention rule.
     */
    public function pruneRevoked(int $months): int
    {
        $threshold = now()->subMonths($months);

        $ids = UserDevice::query()
            ->whereNotNull('revoked_at')
            ->whereNull('blocked_at')
            ->where('revoked_at', '<=', $threshold)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        UserDevice::whereIn('id', $ids)->delete();

        ActivityLogger::log(ActivityModule::Device, ActivityAction::Deleted, null, [
            'bulk' => true,
            'type' => 'device_retention_purged',
            'revoked_months_ago' => $months,
            'device_ids' => $ids->all(),
            'count' => $ids->count(),
        ], causer: null, context: ActivityContext::Scheduler);

        return $ids->count();
    }

    /**
     * Browser sessions ({@see DeviceType::Web}) have their own concurrent
     * limit, entirely separate from every other device type ("app" devices —
     * mobile/tablet/desktop, or unset) — so a phone login and a browser login
     * never compete for the same allowance. Crossing the app limit rejects
     * outright (a named/precious device — the user picks what to revoke via
     * the Devices UI); crossing the browser limit evicts the oldest active
     * browser session instead, since browser sessions are disposable and
     * numerous by nature (work computer, home, a kiosk...).
     *
     * @throws DeviceLimitExceededException
     */
    private function enforceLimit(User $user, ?DeviceType $deviceType): void
    {
        $isBrowser = $deviceType === DeviceType::Web;

        $baseQuery = UserDevice::where('user_id', $user->id)->active();
        $active = ($isBrowser ? $baseQuery->browserType() : $baseQuery->appType())
            ->lockForUpdate()
            ->get();

        $limit = $isBrowser
            ? (int) $user->planFeature('browser_device_limit', config('panel.features.browser_device_limit.default', 15))
            : (int) $user->planFeature('device_limit', config('panel.features.device_limit.default', 1));

        if ($active->count() < $limit) {
            return;
        }

        if (! $isBrowser) {
            throw new DeviceLimitExceededException("Device limit of {$limit} reached.");
        }

        $oldest = $active->sortBy('last_seen_at')->first();

        if ($oldest !== null) {
            $this->revoke($oldest, $user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(DeviceData $data, PersonalAccessToken $token, string $ip): array
    {
        return [
            'token_id' => $token->id,
            'name' => $data->name,
            'model' => $data->model,
            'platform' => $data->platform,
            'os' => $data->os,
            'device_type' => $data->deviceType,
            'app_version' => $data->appVersion,
            'browser' => $data->browser,
            'push_token' => $data->pushToken,
            'push_provider' => $data->pushProvider,
            'ip_address' => $ip,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }
}
