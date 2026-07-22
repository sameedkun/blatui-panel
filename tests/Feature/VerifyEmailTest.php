<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    private function signedVerifyUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_guest_can_verify_via_the_signed_link_without_logging_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertSee('Your email address has been verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_guest_with_a_mismatched_hash_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get($this->signedVerifyUrl($user, sha1('wrong@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_authenticated_user_verifies_their_email(): void
    {
        $user = User::factory()->create(['type' => 'staff', 'email_verified_at' => null]);

        $this->actingAs($user)
            ->get($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertSee('Your email address has been verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_sees_already_verified_message(): void
    {
        $user = User::factory()->create(['type' => 'staff', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertSee('nothing else to do here');
    }

    public function test_mismatched_hash_is_forbidden(): void
    {
        $user = User::factory()->create(['type' => 'staff', 'email_verified_at' => null]);

        $this->actingAs($user)
            ->get($this->signedVerifyUrl($user, sha1('wrong@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_for_a_different_user_is_forbidden(): void
    {
        $user = User::factory()->create(['type' => 'staff', 'email_verified_at' => null]);
        $other = User::factory()->create(['type' => 'staff', 'email_verified_at' => null]);

        $this->actingAs($other)
            ->get($this->signedVerifyUrl($user))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
