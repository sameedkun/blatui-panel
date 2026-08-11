<?php

namespace Tests\Feature\Api\Devices;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: UserDevice, 1: string}
     */
    private function registerDevice(User $user, string $fingerprint = 'device-a'): array
    {
        $newToken = $user->createToken('test');
        $device = app(DeviceService::class)->register($user, new DeviceData(fingerprint: $fingerprint), $newToken->accessToken, '127.0.0.1');

        return [$device, $newToken->plainTextToken];
    }

    public function test_revoked_device_401s_on_the_next_request(): void
    {
        // Revoking deletes the personal_access_tokens row itself (see DeviceService::revoke()
        // and the UserDevice `updated` hook), so a subsequent request with that exact token
        // never even reaches EnsureDeviceIsValid — Sanctum's own auth:sanctum guard already
        // rejects it as an unrecognized token. That single generic 401 is what the spec's
        // "subsequent requests with that token 401" requirement describes.
        $user = User::factory()->app()->create();
        [$device, $plainTextToken] = $this->registerDevice($user);

        app(DeviceService::class)->revoke($device);

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/devices')
            ->assertStatus(401);
    }

    public function test_blocked_device_401s(): void
    {
        $user = User::factory()->app()->create();
        [$device, $plainTextToken] = $this->registerDevice($user);

        app(DeviceService::class)->block($device, 'Reported stolen.');

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/devices')
            ->assertStatus(401);
    }

    public function test_ensure_device_is_valid_reports_the_specific_reason_when_the_token_is_still_live(): void
    {
        // Exercises EnsureDeviceIsValid's own branch logic directly: a device row marked
        // revoked/blocked whose token wasn't deleted (bypassing the model hook via a raw
        // update, simulating e.g. a direct DB write) — the only way the token can still
        // authenticate while the device row disagrees, since every normal service-level
        // mutation deletes the token immediately.
        $user = User::factory()->app()->create();
        [$device, $plainTextToken] = $this->registerDevice($user);
        DB::table('user_devices')->where('id', $device->id)->update(['revoked_at' => now()]);

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/devices')
            ->assertStatus(401)
            ->assertJson(['error' => 'DEVICE_REVOKED']);
    }

    public function test_user_cannot_revoke_another_users_device_by_ulid(): void
    {
        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();

        [$deviceB] = $this->registerDevice($userB);
        [, $tokenA] = $this->registerDevice($userA);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson('/api/v1/devices/'.$deviceB->ulid)
            ->assertStatus(404);

        $this->assertNull($deviceB->fresh()->revoked_at);
    }

    public function test_user_can_revoke_their_own_device(): void
    {
        $user = User::factory()->app()->create();
        [$device, $plainTextToken] = $this->registerDevice($user);

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->deleteJson('/api/v1/devices/'.$device->ulid)
            ->assertOk();

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_revoking_an_already_revoked_device_returns_a_conflict(): void
    {
        $user = User::factory()->app()->create();
        [$device] = $this->registerDevice($user, 'device-a');
        [, $otherToken] = $this->registerDevice($user, 'device-b');

        app(DeviceService::class)->revoke($device);

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->deleteJson('/api/v1/devices/'.$device->ulid)
            ->assertStatus(409)
            ->assertJson(['errors' => ['code' => 'DEVICE_ALREADY_REVOKED']]);
    }

    public function test_device_list_flags_which_device_is_the_current_one(): void
    {
        $user = User::factory()->app()->create();
        [$deviceA, $tokenA] = $this->registerDevice($user, 'device-a');
        [$deviceB] = $this->registerDevice($user, 'device-b');

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/devices')
            ->assertOk();

        $byUlid = collect($response->json('data'))->keyBy('ulid');

        $this->assertTrue($byUlid[$deviceA->ulid]['current']);
        $this->assertFalse($byUlid[$deviceB->ulid]['current']);
    }

    public function test_user_can_revoke_every_device_except_the_current_one(): void
    {
        $user = User::factory()->app()->create();
        [$current, $currentToken] = $this->registerDevice($user, 'device-current');
        [$other] = $this->registerDevice($user, 'device-other');

        $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->deleteJson('/api/v1/devices/others')
            ->assertOk()
            ->assertJsonPath('data.revoked', 1);

        $this->assertNull($current->fresh()->revoked_at);
        $this->assertNotNull($other->fresh()->revoked_at);
    }

    public function test_revoke_others_is_a_no_op_when_there_are_no_other_devices(): void
    {
        $user = User::factory()->app()->create();
        [, $token] = $this->registerDevice($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/devices/others')
            ->assertOk()
            ->assertJsonPath('data.revoked', 0);
    }
}
