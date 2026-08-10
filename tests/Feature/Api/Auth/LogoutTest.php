<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: UserDevice, 2: string}
     */
    private function loggedInUser(): array
    {
        $user = User::factory()->app()->create();
        $token = $user->createToken('device');

        $device = app(DeviceService::class)->register(
            $user,
            new DeviceData(fingerprint: 'device-a'),
            $token->accessToken,
            '127.0.0.1',
        );

        return [$user, $device, $token->plainTextToken];
    }

    public function test_logout_revokes_the_calling_device_and_deletes_its_token(): void
    {
        [$user, $device, $plainTextToken] = $this->loggedInUser();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertNull($device->fresh()->token_id);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $device->fresh()->token_id]);

        // The device-scoped audit row DeviceService::revoke() always writes.
        Activity::where('subject_id', $device->id)
            ->where('subject_type', UserDevice::class)
            ->where('event', 'revoked')
            ->firstOrFail();

        // The user-scoped row that makes this show up on the user's own
        // profile Activity tab (Activity::forSubject($user)) — device.name is
        // null on this fixture (no name was passed at registration), so
        // device_id is what proves which device it was.
        $logoutActivity = Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('log_name', 'authentication')
            ->where('event', 'logout')
            ->firstOrFail();

        $this->assertSame($user->id, $logoutActivity->causer_id);
        $this->assertSame($device->ulid, $logoutActivity->properties['device_id']);
    }

    public function test_the_revoked_token_can_no_longer_be_used(): void
    {
        [, , $plainTextToken] = $this->loggedInUser();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/devices')
            ->assertStatus(401);
    }

    public function test_logout_only_revokes_the_calling_device_not_every_device(): void
    {
        [$user, , $plainTextToken] = $this->loggedInUser();

        $otherToken = $user->createToken('other-device');
        $otherDevice = app(DeviceService::class)->register(
            $user,
            new DeviceData(fingerprint: 'device-b'),
            $otherToken->accessToken,
            '127.0.0.1',
        );

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertNull($otherDevice->fresh()->revoked_at);
        $this->assertNotNull(PersonalAccessToken::find($otherDevice->fresh()->token_id));
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertStatus(401);
    }
}
