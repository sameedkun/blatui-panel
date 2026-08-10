<?php

namespace Tests\Feature;

use App\Enum\UserType;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'a-real-password',
            'password_confirmation' => 'a-real-password',
        ], $overrides);
    }

    public function test_signup_creates_an_app_user_and_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/signup', $this->payload())
            ->assertCreated()
            ->assertJson(['status' => true])
            ->assertJsonMissingPath('data.token');

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame(UserType::App, $user->type);
        $this->assertTrue(Hash::check('a-real-password', $user->password));
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $row = Activity::where('subject_id', $user->id)->where('event', 'created')->firstOrFail();
        $this->assertSame('user', $row->properties['module']);
        $this->assertSame('self', $row->properties['initiated_by']);
        $this->assertSame($user->id, $row->causer_id);
    }

    public function test_signup_rejects_a_duplicate_email(): void
    {
        User::factory()->app()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/signup', $this->payload())
            ->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_signup_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/signup', $this->payload(['password_confirmation' => 'different']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
