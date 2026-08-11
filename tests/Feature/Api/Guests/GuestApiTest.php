<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Mockery;
use Tests\TestCase;

class GuestApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} App user, authenticated with a registered device. */
    private function authenticatedAppUser(array $attributes = []): array
    {
        $user = User::factory()->app()->create($attributes);
        $token = $user->createToken('device');

        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-a'), $token->accessToken, '127.0.0.1');

        return [$user, $token->plainTextToken];
    }

    /** @return array{0: User, 1: string} Guest, authenticated with no device (guests never register one). */
    private function authenticatedGuest(array $attributes = []): array
    {
        // UserFactory::guest() doesn't clear banned_at the way app() does, and
        // the base definition() randomly bans ~50% of factory rows — force it
        // unbanned so this helper is deterministic.
        $guest = User::factory()->guest()->create(['banned_at' => null, 'ban_reason' => null, ...$attributes]);
        $token = $guest->createToken('guest');

        return [$guest, $token->plainTextToken];
    }

    /**
     * Socialite::fake() only overrides redirect()/user() — userFromToken()
     * falls through to the real provider (a live HTTP call), so it isn't
     * usable here. This mocks the facade directly instead.
     */
    private function mockSocialiteProvider(string $provider, array $attributes): void
    {
        $fakeUser = SocialiteTwoUser::fake($attributes);

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('stateless')->andReturnSelf()->zeroOrMoreTimes();
        $providerMock->shouldReceive('userFromToken')->once()->andReturn($fakeUser);

        Socialite::shouldReceive('driver')->with($provider)->once()->andReturn($providerMock);
    }

    // -------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------

    public function test_creates_a_guest_account_with_a_generated_email_and_a_token(): void
    {
        $response = $this->postJson('/api/v1/guests')->assertCreated();

        $email = $response->json('data.user.email');
        $this->assertMatchesRegularExpression('/^guest_[a-z0-9]+@/', $email);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));

        $this->assertDatabaseHas('users', ['email' => $email, 'type' => 'guest']);
    }

    public function test_guest_creation_generates_a_real_external_id(): void
    {
        $response = $this->postJson('/api/v1/guests')->assertCreated();

        $this->assertNotEmpty($response->json('data.user.external_id'));
    }

    // -------------------------------------------------------------------
    // Route gating (EnsureUserType)
    // -------------------------------------------------------------------

    public function test_guest_can_reach_subscription_routes(): void
    {
        [, $token] = $this->authenticatedGuest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk();
    }

    public function test_guest_cannot_reach_profile_devices_tickets_or_logout(): void
    {
        [, $token] = $this->authenticatedGuest();
        $auth = fn () => $this->withHeader('Authorization', 'Bearer '.$token);

        $auth()->getJson('/api/v1/me')->assertStatus(403);
        $auth()->getJson('/api/v1/devices')->assertStatus(403);
        $auth()->getJson('/api/v1/tickets')->assertStatus(403);
        $auth()->postJson('/api/v1/logout')->assertStatus(403);
    }

    public function test_app_user_cannot_reach_guest_convert_routes(): void
    {
        [, $token] = $this->authenticatedAppUser();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert', ['email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------
    // Convert by email + password
    // -------------------------------------------------------------------

    public function test_guest_can_convert_via_email_and_password_and_keeps_using_the_same_token(): void
    {
        [$guest, $token] = $this->authenticatedGuest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert', [
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'name' => 'New User',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'newuser@example.com');

        $guest->refresh();
        $this->assertSame('app', $guest->type->value);
        $this->assertNull($guest->email_verified_at);

        // Same guest row, same id — the existing token must still authenticate.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk();
    }

    public function test_convert_via_email_rejects_an_email_already_taken(): void
    {
        User::factory()->app()->create(['email' => 'taken@example.com']);
        [, $token] = $this->authenticatedGuest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert', [
                'email' => 'taken@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    // -------------------------------------------------------------------
    // Convert via provider
    // -------------------------------------------------------------------

    public function test_convert_via_google_creates_a_new_account_and_keeps_the_same_token(): void
    {
        [$guest, $token] = $this->authenticatedGuest();

        $this->mockSocialiteProvider('google', [
            'id' => 'google-123',
            'email' => 'fresh@example.com',
            'name' => 'Fresh Person',
            'email_verified' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert/google', ['token' => 'fake-google-access-token'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'fresh@example.com')
            ->assertJsonMissingPath('data.token');

        $guest->refresh();
        $this->assertSame('app', $guest->type->value);
        $this->assertSame('google-123', $guest->google_id);
        $this->assertNotNull($guest->email_verified_at);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subscription')
            ->assertOk();
    }

    public function test_convert_via_apple_uses_the_client_supplied_name_since_apples_id_token_never_carries_one(): void
    {
        [$guest, $token] = $this->authenticatedGuest();

        // Apple's id_token JWT never carries a name claim — only the first
        // authorization's POST body does, which this endpoint doesn't see —
        // so the request's own `name` field is the only source for it here.
        $this->mockSocialiteProvider('apple', [
            'id' => 'apple-789',
            'email' => 'apple-user@example.com',
            'name' => null,
            'email_verified' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert/apple', [
                'token' => 'fake-apple-id-token',
                'name' => 'Apple Person',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'apple-user@example.com');

        $this->assertSame('Apple Person', $guest->fresh()->name);
        $this->assertSame('apple-789', $guest->fresh()->apple_id);
    }

    public function test_convert_via_provider_merges_into_an_existing_account_and_issues_a_new_token(): void
    {
        $destination = User::factory()->app()->create(['email' => 'existing@example.com', 'google_id' => null]);
        [$guest, $guestToken] = $this->authenticatedGuest();

        $this->mockSocialiteProvider('google', [
            'id' => 'google-456',
            'email' => 'existing@example.com',
            'name' => 'Existing Person',
            'email_verified' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$guestToken)
            ->postJson('/api/v1/guests/convert/google', ['token' => 'fake-google-access-token'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'existing@example.com');

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);

        $this->assertNull(User::find($guest->id), 'Guest row should be force-deleted after merge');
        $this->assertSame('google-456', $destination->fresh()->google_id);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $guest->id,
            'tokenable_type' => User::class,
        ]);

        // Sanctum's RequestGuard caches the resolved user for the guard
        // instance's lifetime, and the test client reuses that instance
        // across sequential calls within one test — without this, the old
        // token would still "work" here because the earlier successful
        // authentication (the convert POST above) is cached, not because the
        // token is actually still valid (a real, separate request wouldn't
        // have that cache and would correctly get a fresh 401).
        Auth::forgetGuards();

        // Old guest token is dead.
        $this->withHeader('Authorization', 'Bearer '.$guestToken)
            ->getJson('/api/v1/subscription')
            ->assertStatus(401);

        // New token works.
        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/subscription')
            ->assertOk();
    }

    public function test_unsupported_provider_404s(): void
    {
        [, $token] = $this->authenticatedGuest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert/facebook', ['token' => 'x'])
            ->assertStatus(404);
    }

    public function test_convert_via_provider_rejects_an_unverifiable_token(): void
    {
        [, $token] = $this->authenticatedGuest();

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('userFromToken')->once()->andThrow(new \RuntimeException('invalid_grant'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($providerMock);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/guests/convert/google', ['token' => 'garbage'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'PROVIDER_TOKEN_INVALID');
    }
}
