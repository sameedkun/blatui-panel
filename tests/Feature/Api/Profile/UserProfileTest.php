<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserProfileTest extends TestCase
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

    public function test_me_returns_the_authenticated_users_profile(): void
    {
        [$user, $token] = $this->authenticatedUser(['name' => 'Jane Doe']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Jane Doe')
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_update_changes_the_name_and_logs_the_diff(): void
    {
        [$user, $token] = $this->authenticatedUser(['name' => 'Old Name']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);

        $activity = Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame(['name' => 'New Name'], $activity->properties['attributes']);
        $this->assertSame(['name' => 'Old Name'], $activity->properties['old']);
    }

    public function test_update_rejects_a_name_longer_than_sixty_characters(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['name' => str_repeat('a', 61)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_update_stores_the_avatar_on_the_default_disk_and_replaces_the_old_one(): void
    {
        Storage::fake();

        [$user, $token] = $this->authenticatedUser();
        $user->forceFill(['avatar' => 'avatars/old.jpg'])->save();
        Storage::put('avatars/old.jpg', 'old-contents');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['avatar' => UploadedFile::fake()->image('avatar.jpg')])
            ->assertOk();

        $user->refresh();
        $this->assertNotSame('avatars/old.jpg', $user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::assertMissing('avatars/old.jpg');
        Storage::assertExists($user->avatar);
        $this->assertNotNull($response->json('data.user.avatar'));

        // Never logs the raw path — only a boolean flag, same convention
        // password changes use.
        $activity = Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertTrue($activity->properties['avatar_changed']);
        $this->assertArrayNotHasKey('avatar', $activity->properties['attributes'] ?? []);
    }

    public function test_update_rejects_a_non_image_avatar(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['avatar' => UploadedFile::fake()->create('resume.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_update_password_succeeds_with_the_correct_current_password(): void
    {
        [$user, $token] = $this->authenticatedUser(['password' => 'old-password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_changed_at);

        // Never logs the raw password — only a boolean flag.
        $activity = Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertTrue($activity->properties['password_changed']);
    }

    public function test_update_password_rejects_the_wrong_current_password(): void
    {
        [$user, $token] = $this->authenticatedUser(['password' => 'old-password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_update_password_is_rate_limited(): void
    {
        [, $token] = $this->authenticatedUser(['password' => 'old-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->putJson('/api/v1/me/password', [
                    'current_password' => 'wrong-password',
                    'password' => 'a-brand-new-password',
                    'password_confirmation' => 'a-brand-new-password',
                ])
                ->assertStatus(422);
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertStatus(429);
    }

    public function test_update_password_does_not_revoke_the_calling_devices_token(): void
    {
        // Matches Livewire\Admin\Account\Index::updatePassword() precedent —
        // a plain password change never touches other sessions/devices;
        // that's the separate, explicit "log out other devices" action.
        [$user, $token] = $this->authenticatedUser(['password' => 'old-password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk();
    }
}
