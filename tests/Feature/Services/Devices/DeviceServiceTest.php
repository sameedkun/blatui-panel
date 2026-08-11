<?php

namespace Tests\Feature\Services\Devices;

use App\Enum\DeviceType;
use App\Exceptions\DeviceBlockedException;
use App\Exceptions\DeviceLimitExceededException;
use App\Jobs\Device\ResolveDeviceLocation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DeviceService
    {
        return app(DeviceService::class);
    }

    private function tokenFor(User $user): PersonalAccessToken
    {
        return $user->createToken('test')->accessToken;
    }

    /** Registers with a loopback IP — LocationService short-circuits on it, so no real HTTP call happens. */
    private function register(User $user, string $fingerprint, ?PersonalAccessToken $token = null): UserDevice
    {
        return $this->service()->register(
            $user,
            new DeviceData(fingerprint: $fingerprint),
            $token ?? $this->tokenFor($user),
            '127.0.0.1',
        );
    }

    private function registerBrowser(User $user, string $fingerprint, ?PersonalAccessToken $token = null): UserDevice
    {
        return $this->service()->register(
            $user,
            new DeviceData(fingerprint: $fingerprint, deviceType: DeviceType::Web),
            $token ?? $this->tokenFor($user),
            '127.0.0.1',
        );
    }

    private function subscribeWithDeviceLimit(User $user, int $limit): void
    {
        $this->subscribeWithLimits($user, deviceLimit: $limit);
    }

    private function subscribeWithLimits(User $user, ?int $deviceLimit = null, ?int $browserDeviceLimit = null): void
    {
        $features = array_filter([
            'device_limit' => $deviceLimit,
            'browser_device_limit' => $browserDeviceLimit,
        ], fn (?int $v): bool => $v !== null);

        $plan = Plan::factory()->create(['features' => $features]);

        Subscription::factory()->for($user)->for($plan)->create([
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_device_limit_is_enforced(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $this->register($user, 'device-a');

        $this->expectException(DeviceLimitExceededException::class);

        $this->register($user, 'device-b');
    }

    public function test_device_limit_is_enforced_under_back_to_back_registrations(): void
    {
        // Approximates the "two concurrent login requests" requirement: PHPUnit is
        // single-process against a shared SQLite :memory: connection, so true
        // multi-process concurrency can't be exercised here. This proves the guard
        // triggers correctly at the boundary; the lockForUpdate() transaction in
        // DeviceService::register() is what carries the actual production guarantee
        // against a real concurrent race.
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 2);

        $this->register($user, 'device-a');
        $this->register($user, 'device-b');

        $this->assertSame(2, UserDevice::where('user_id', $user->id)->active()->count());

        $this->expectException(DeviceLimitExceededException::class);

        $this->register($user, 'device-c');
    }

    public function test_browser_device_limit_is_enforced_separately_from_the_app_device_limit(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithLimits($user, deviceLimit: 1, browserDeviceLimit: 1);

        $this->register($user, 'phone'); // fills the app bucket

        // The browser bucket is untouched by the app device above, so this
        // must succeed even though the account is already "at its limit".
        $browser = $this->registerBrowser($user, 'chrome-on-windows');

        $this->assertSame(DeviceType::Web, $browser->device_type);
        $this->assertSame(2, UserDevice::where('user_id', $user->id)->active()->count());
    }

    public function test_browser_device_limit_overflow_evicts_the_oldest_browser_session_instead_of_rejecting(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithLimits($user, browserDeviceLimit: 2);

        $oldest = $this->registerBrowser($user, 'browser-a');
        $oldest->forceFill(['last_seen_at' => now()->subDay()])->saveQuietly();
        $newer = $this->registerBrowser($user, 'browser-b');

        // Crossing the limit must not throw — it evicts $oldest instead.
        $third = $this->registerBrowser($user, 'browser-c');

        $this->assertNotNull($oldest->fresh()->revoked_at);
        $this->assertNull($newer->fresh()->revoked_at);
        $this->assertNull($third->fresh()->revoked_at);
        $this->assertSame(2, UserDevice::where('user_id', $user->id)->active()->browserType()->count());
    }

    public function test_relogin_on_existing_fingerprint_reuses_the_row_and_swaps_the_token(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $firstToken = $this->tokenFor($user);
        $device = $this->register($user, 'same-device', $firstToken);

        $secondToken = $this->tokenFor($user);
        $reloggedDevice = $this->register($user, 'same-device', $secondToken);

        $this->assertSame($device->id, $reloggedDevice->id);
        $this->assertSame(1, UserDevice::where('user_id', $user->id)->count());
        $this->assertSame($secondToken->id, $reloggedDevice->fresh()->token_id);
    }

    public function test_relogin_never_counts_against_the_device_limit(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $this->register($user, 'same-device');

        // Re-login on the same fingerprint must succeed even though the account is
        // already at its limit of 1 — it doesn't create a second row.
        $this->register($user, 'same-device');

        $this->assertSame(1, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_revoked_device_reactivates_under_limit(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 2);

        $device = UserDevice::factory()->for($user)->revoked()->create([
            'device_fingerprint' => hash('sha256', 'revoked-device'),
        ]);

        $reactivated = $this->register($user, 'revoked-device');

        $this->assertSame($device->id, $reactivated->id);
        $this->assertNull($reactivated->fresh()->revoked_at);
        $this->assertNotNull($reactivated->fresh()->token_id);
    }

    public function test_blocked_device_can_never_relogin(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 2);

        UserDevice::factory()->for($user)->blocked()->create([
            'device_fingerprint' => hash('sha256', 'blocked-device'),
        ]);

        $this->expectException(DeviceBlockedException::class);

        $this->register($user, 'blocked-device');
    }

    public function test_user_id_and_fingerprint_pair_is_unique(): void
    {
        $user = User::factory()->app()->create();

        UserDevice::factory()->for($user)->create([
            'device_fingerprint' => hash('sha256', 'dupe'),
        ]);

        $this->expectException(QueryException::class);

        UserDevice::factory()->for($user)->create([
            'device_fingerprint' => hash('sha256', 'dupe'),
        ]);
    }

    public function test_fingerprint_is_stored_hashed_never_in_plaintext(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $device = $this->register($user, 'raw-client-value');

        $this->assertNotSame('raw-client-value', $device->device_fingerprint);
        $this->assertSame(hash('sha256', 'raw-client-value'), $device->device_fingerprint);
    }

    public function test_registration_records_the_server_resolved_ip(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $device = $this->register($user, 'device-a');

        $this->assertSame('127.0.0.1', $device->ip_address);
    }

    public function test_revoke_deletes_the_token_row(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $token = $this->tokenFor($user);
        $device = $this->register($user, 'device-a', $token);

        $this->service()->revoke($device);

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertNull($device->fresh()->token_id);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_revoking_an_already_revoked_device_is_a_no_op(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $device = $this->register($user, 'device-a');

        $this->service()->revoke($device);
        $firstRevokedAt = $device->fresh()->revoked_at;

        $this->travel(1)->minute();
        $this->service()->revoke($device->fresh());

        $this->assertTrue($device->fresh()->revoked_at->eq($firstRevokedAt));

        $this->assertSame(
            1,
            Activity::where('subject_type', UserDevice::class)
                ->where('subject_id', $device->id)
                ->where('event', 'revoked')
                ->count(),
        );
    }

    public function test_block_deletes_the_token_row(): void
    {
        $user = User::factory()->app()->create();
        $this->subscribeWithDeviceLimit($user, 1);

        $token = $this->tokenFor($user);
        $device = $this->register($user, 'device-a', $token);

        $this->service()->block($device, 'Suspicious activity reported.');

        $this->assertNotNull($device->fresh()->blocked_at);
        $this->assertNull($device->fresh()->token_id);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_unblock_does_not_restore_the_token(): void
    {
        $user = User::factory()->app()->create();

        $device = UserDevice::factory()->for($user)->blocked()->create();

        $this->service()->unblock($device);

        $this->assertNull($device->fresh()->blocked_at);
        $this->assertNull($device->fresh()->token_id);
    }

    public function test_touch_requeues_location_only_when_the_ip_actually_changes(): void
    {
        Queue::fake(); // '203.0.113.5' below is a real-looking IP — never let the job actually run and hit a provider.

        $user = User::factory()->app()->create();
        $device = $this->register($user, 'device-a'); // pushes one job itself, registering at '127.0.0.1'

        // Same IP, forced past the throttle window — no change, so no re-resolve needed.
        $device->forceFill(['last_seen_at' => now()->subMinutes(10)])->saveQuietly();
        $this->service()->touch($device, '127.0.0.1');
        $this->assertSame('127.0.0.1', $device->fresh()->ip_address);
        Queue::assertPushed(ResolveDeviceLocation::class, 1); // still just the one from register()

        // A genuinely different IP past the throttle window updates ip_address and re-queues.
        $device->forceFill(['last_seen_at' => now()->subMinutes(10)])->saveQuietly();
        $this->service()->touch($device, '203.0.113.5');
        $this->assertSame('203.0.113.5', $device->fresh()->ip_address);
        Queue::assertPushed(ResolveDeviceLocation::class, 2);
    }
}
