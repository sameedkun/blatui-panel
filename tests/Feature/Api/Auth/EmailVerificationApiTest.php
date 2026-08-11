<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Auth\UrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmailVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function signedVerifyUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), [
            'id' => $user->external_id,
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_verify_marks_the_email_as_verified_and_logs_it(): void
    {
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        $this->getJson($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'verified')
            ->firstOrFail();
    }

    public function test_verify_is_idempotent_for_an_already_verified_account(): void
    {
        $user = User::factory()->app()->create(['email_verified_at' => now()]);

        $this->getJson($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertJson(['status' => true]);

        // No duplicate Verified event — only ever the one from account creation, none here.
        $this->assertSame(0, Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'verified')
            ->count());
    }

    public function test_verify_rejects_a_mismatched_hash(): void
    {
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        $this->getJson($this->signedVerifyUrl($user, sha1('someone-else@example.com')))
            ->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_rejects_an_expired_link(): void
    {
        $user = User::factory()->app()->create(['email_verified_at' => null]);
        $url = $this->signedVerifyUrl($user);

        $this->travel(61)->minutes();

        $this->getJson($url)->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_404s_for_an_unknown_user(): void
    {
        $url = URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), [
            'id' => (string) Str::ulid(),
            'hash' => sha1('nobody@example.com'),
        ]);

        $this->getJson($url)->assertStatus(404);
    }

    /**
     * Regression test: the signature must be generated against THIS app's own
     * real host, never the frontend's — Laravel validates a signed URL against
     * the *receiving* request's own absolute URL, so a signature computed
     * against a different origin (the old behavior) could never validate once
     * relayed here, and would always 403 with an "invalid signature".
     */
    public function test_frontend_verification_link_validates_against_the_real_api_host(): void
    {
        config(['panel.auth_url_mode' => 'frontend', 'panel.frontend_url' => 'https://quixure.com']);

        $user = User::factory()->app()->create(['email_verified_at' => null]);

        $frontendUrl = app(UrlResolver::class)->verificationUrl($user);
        $this->assertStringStartsWith('https://quixure.com/email/verify?', $frontendUrl);

        parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $params);
        $this->assertSame($user->external_id, $params['id']);
        $this->assertArrayHasKey('signature', $params);

        // The frontend's job: relay id/hash/expires/signature, unmodified, to
        // the real API host — id/hash go back into the path, matching this
        // route's own shape (only expires/signature ride as query params).
        $apiPath = "/api/v1/email/verify/{$params['id']}/{$params['hash']}?".http_build_query([
            'expires' => $params['expires'],
            'signature' => $params['signature'],
        ]);

        $this->getJson($apiPath)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_a_new_verification_link_and_logs_it(): void
    {
        Notification::fake();

        $user = User::factory()->app()->create(['email_verified_at' => null]);

        $this->postJson('/api/v1/email/resend', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['status' => true]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);

        Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('event', 'sent')
            ->firstOrFail();
    }

    public function test_resend_is_a_no_op_for_an_already_verified_account(): void
    {
        Notification::fake();

        $user = User::factory()->app()->create(['email_verified_at' => now()]);

        $this->postJson('/api/v1/email/resend', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['status' => true]);

        Notification::assertNothingSent();
        $this->assertSame(0, Activity::where('subject_id', $user->id)->where('event', 'sent')->count());
    }

    public function test_resend_does_not_leak_whether_the_email_exists(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/email/resend', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['status' => true]);

        Notification::assertNothingSent();
    }

    public function test_resend_never_targets_staff_or_guest_accounts(): void
    {
        Notification::fake();

        User::factory()->create(['type' => 'staff', 'email' => 'staff@example.com', 'email_verified_at' => null]);
        User::factory()->guest()->create(['email' => 'guest@example.com', 'email_verified_at' => null]);

        $this->postJson('/api/v1/email/resend', ['email' => 'staff@example.com'])->assertOk();
        $this->postJson('/api/v1/email/resend', ['email' => 'guest@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_resend_is_rate_limited(): void
    {
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/email/resend', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/v1/email/resend', ['email' => $user->email])->assertStatus(429);
    }
}
