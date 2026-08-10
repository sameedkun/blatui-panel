<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->app()->create(array_merge([
            'email' => 'jane@example.com',
            'password' => 'old-password',
        ], $attributes));
    }

    public function test_forgot_sends_a_reset_link_and_logs_it(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['status' => true]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'sent')
            ->firstOrFail();
    }

    public function test_forgot_does_not_leak_whether_the_email_exists(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['status' => true]);

        Notification::assertNothingSent();
    }

    public function test_forgot_never_targets_staff_or_guest_accounts(): void
    {
        Notification::fake();

        User::factory()->create(['type' => 'staff', 'email' => 'staff@example.com']);
        User::factory()->guest()->create(['email' => 'guest@example.com']);

        $this->postJson('/api/v1/password/forgot', ['email' => 'staff@example.com'])->assertOk();
        $this->postJson('/api/v1/password/forgot', ['email' => 'guest@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_forgot_is_rate_limited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertStatus(429);
    }

    public function test_reset_changes_the_password_and_logs_it(): void
    {
        $user = $this->makeUser(['password_changed_at' => null]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/password/reset', [
            'email' => Crypt::encryptString($user->email),
            'token' => $token,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));

        // TouchPasswordChangedAt listens for the same PasswordReset event this
        // fires — bumped for free, no need for the controller to set it itself.
        $this->assertNotNull($user->fresh()->password_changed_at);

        Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('log_name', 'authentication')
            ->where('event', 'password_reset')
            ->firstOrFail();
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/password/reset', [
            'email' => Crypt::encryptString($user->email),
            'token' => 'not-a-real-token',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_rejects_an_email_that_is_not_a_valid_encrypted_payload(): void
    {
        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/password/reset', [
            'email' => 'plain-text-not-encrypted@example.com',
            'token' => $token,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_never_targets_a_staff_or_guest_account(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'email' => 'staff@example.com', 'password' => 'old-password']);
        $token = Password::broker()->createToken($staff);

        $this->postJson('/api/v1/password/reset', [
            'email' => Crypt::encryptString($staff->email),
            'token' => $token,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $staff->fresh()->password));
    }

    public function test_reset_is_rate_limited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/password/reset', [
                'email' => Crypt::encryptString($user->email),
                'token' => 'not-a-real-token',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/password/reset', [
            'email' => Crypt::encryptString($user->email),
            'token' => 'not-a-real-token',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertStatus(429);
    }
}
