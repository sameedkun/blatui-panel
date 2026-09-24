<?php

namespace Tests\Feature\Api\Auth;

use App\Enum\UserType;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
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

    public function test_signup_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/signup', $this->payload(['email' => "user{$i}@example.com"]));
        }

        $this->postJson('/api/v1/signup', $this->payload(['email' => 'one-too-many@example.com']))
            ->assertStatus(429);
    }

    /**
     * Signing up against an email that already belongs to a staff, guest, or trashed
     * account must be indistinguishable from the ordinary "already taken" app-user
     * duplicate — anything else would let an unauthenticated caller enumerate staff
     * emails (a targeted phishing list) by sweeping /signup. SignupRequest's own
     * `unique` rule is scoped to active app users only, so these three cases fall
     * through to AuthController::signup()'s UniqueConstraintViolationException catch
     * instead — this asserts that fallback produces the exact same response.
     */
    public function test_signup_against_a_staff_guest_or_trashed_email_matches_the_ordinary_duplicate_response(): void
    {
        $duplicateAppUser = User::factory()->app()->create(['email' => 'app-dup@example.com']);
        $duplicateResponse = $this->postJson('/api/v1/signup', $this->payload(['email' => $duplicateAppUser->email]));

        $staff = User::factory()->create(['type' => 'staff', 'email' => 'staff-dup@example.com']);
        $staffResponse = $this->postJson('/api/v1/signup', $this->payload(['email' => $staff->email]));

        $guest = User::factory()->guest()->create(['email' => 'guest-dup@example.com']);
        $guestResponse = $this->postJson('/api/v1/signup', $this->payload(['email' => $guest->email]));

        $trashed = User::factory()->app()->create(['email' => 'trashed-dup@example.com']);
        $trashed->delete();
        $trashedResponse = $this->postJson('/api/v1/signup', $this->payload(['email' => $trashed->email]));

        // request_id is unique per request by design, so it's the one key excluded.
        foreach ([$staffResponse, $guestResponse, $trashedResponse] as $response) {
            $response->assertStatus($duplicateResponse->status());
            $response->assertJsonStructure(['request_id']);
            $this->assertSame(Arr::except($duplicateResponse->json(), 'request_id'), Arr::except($response->json(), 'request_id'));
        }

        // No new row was created for any of them — the collision was refused, not merged.
        $this->assertSame(1, User::where('email', 'staff-dup@example.com')->count());
        $this->assertSame(1, User::where('email', 'guest-dup@example.com')->count());
        $this->assertSame(1, User::withTrashed()->where('email', 'trashed-dup@example.com')->count());
    }

    public function test_signup_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/signup', $this->payload(['password_confirmation' => 'different']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
