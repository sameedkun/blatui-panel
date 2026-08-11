<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Account\DeletionService;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function authenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->app()->create($attributes);
        $token = $user->createToken('device');

        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-a'), $token->accessToken, '127.0.0.1');

        return [$user, $token->plainTextToken];
    }

    public function test_user_can_request_their_own_deletion(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete', ['reason' => 'No longer needed'])
            ->assertOk()
            ->assertJsonPath('data.user.pending_deletion.can_cancel', true);

        $user->refresh();
        $this->assertTrue($user->isPendingDeletion());
        $this->assertSame('user', $user->deletion_requested_by);
        $this->assertSame('No longer needed', $user->deletion_reason);
    }

    public function test_reason_is_optional(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete', [])
            ->assertOk();
    }

    public function test_requesting_deletion_twice_returns_a_conflict(): void
    {
        [$user, $token] = $this->authenticatedUser();

        app(DeletionService::class)->requestByUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete', [])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'DELETION_NOT_AVAILABLE');
    }

    public function test_user_can_cancel_their_own_pending_deletion(): void
    {
        [$user, $token] = $this->authenticatedUser();
        app(DeletionService::class)->requestByUser($user, 'Changed my mind later');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete/cancel')
            ->assertOk()
            ->assertJsonPath('data.user.pending_deletion', null);

        $this->assertFalse($user->fresh()->isPendingDeletion());
    }

    public function test_cancelling_with_nothing_pending_is_forbidden(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete/cancel')
            ->assertStatus(403);
    }

    public function test_user_cannot_cancel_an_admin_initiated_deletion(): void
    {
        [$user, $token] = $this->authenticatedUser();
        app(DeletionService::class)->requestByAdmin($user, 'Policy violation');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/delete/cancel')
            ->assertStatus(403);

        $this->assertTrue($user->fresh()->isPendingDeletion());
    }
}
