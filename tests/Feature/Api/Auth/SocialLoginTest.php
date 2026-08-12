<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Mockery;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'token' => 'fake-google-access-token',
            'device' => [
                'fingerprint' => 'device-fingerprint-a',
                'name' => "Jane's iPhone",
                'platform' => 'ios',
                'type' => 'mobile',
            ],
        ], $overrides);
    }

    /** Socialite::fake() doesn't cover userFromToken() — see GuestApiTest's own copy of this helper. */
    private function mockSocialiteProvider(string $provider, array $attributes): void
    {
        $fakeUser = SocialiteTwoUser::fake($attributes);

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('stateless')->andReturnSelf()->zeroOrMoreTimes();
        $providerMock->shouldReceive('userFromToken')->once()->andReturn($fakeUser);

        Socialite::shouldReceive('driver')->with($provider)->once()->andReturn($providerMock);
    }

    public function test_new_google_user_creates_an_account_registers_the_device_and_returns_a_token(): void
    {
        $this->mockSocialiteProvider('google', [
            'id' => 'google-123',
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/social/google', $this->payload())
            ->assertOk()
            ->assertJson(['status' => true])
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('app', $user->type->value);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);

        $device = UserDevice::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(hash('sha256', 'device-fingerprint-a'), $device->device_fingerprint);

        $token = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertOk();

        $this->assertNotNull($user->fresh()->last_login);
        Activity::where('subject_id', $user->id)->where('event', 'login')->firstOrFail();
    }

    public function test_returning_google_user_logs_in_instead_of_creating_a_duplicate(): void
    {
        $existing = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => 'google-123']);

        $this->mockSocialiteProvider('google', [
            'id' => 'google-123',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $this->assertSame(1, User::where('email', 'jane@example.com')->count());
        $this->assertSame($existing->id, User::where('email', 'jane@example.com')->firstOrFail()->id);
    }

    public function test_an_existing_account_with_a_matching_email_but_no_provider_id_gets_linked(): void
    {
        $existing = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => null]);

        $this->mockSocialiteProvider('google', [
            'id' => 'google-456',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false);

        $this->assertSame('google-456', $existing->fresh()->google_id);
    }

    public function test_rejects_an_unverifiable_token(): void
    {
        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('userFromToken')->once()->andThrow(new \RuntimeException('invalid_grant'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($providerMock);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'PROVIDER_TOKEN_INVALID');
    }

    public function test_rejects_a_token_with_no_email(): void
    {
        $this->mockSocialiteProvider('google', ['id' => 'google-789', 'email' => null]);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'PROVIDER_EMAIL_MISSING');
    }

    public function test_rejects_a_banned_account_without_issuing_a_token(): void
    {
        $user = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => 'google-123', 'banned_at' => now(), 'ban_reason' => 'Fraud']);

        $this->mockSocialiteProvider('google', ['id' => 'google-123', 'email' => 'jane@example.com']);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertStatus(403)
            ->assertJson(['errors' => ['code' => 'ACCOUNT_BANNED']]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_rejects_a_soft_deleted_account(): void
    {
        $user = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => 'google-123']);
        $user->delete();

        $this->mockSocialiteProvider('google', ['id' => 'google-123', 'email' => 'jane@example.com']);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertStatus(410);
    }

    public function test_rejects_a_blocked_device_without_issuing_a_working_token(): void
    {
        $user = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => 'google-123']);
        $token = $user->createToken('seed');
        $device = app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-fingerprint-a'), $token->accessToken, '127.0.0.1');
        app(DeviceService::class)->block($device, 'Reported stolen.');

        $tokensBefore = $user->tokens()->count();

        $this->mockSocialiteProvider('google', ['id' => 'google-123', 'email' => 'jane@example.com']);

        $this->postJson('/api/v1/social/google', $this->payload())
            ->assertStatus(403)
            ->assertJson(['errors' => ['code' => 'DEVICE_BLOCKED']]);

        $this->assertSame($tokensBefore, $user->tokens()->count());
    }

    public function test_rejects_past_the_device_limit_without_issuing_a_working_token(): void
    {
        $user = User::factory()->app()->create(['email' => 'jane@example.com', 'google_id' => 'google-123']);

        $plan = Plan::factory()->create(['features' => ['device_limit' => 1]]);
        Subscription::factory()->for($user)->for($plan)->create(['status' => 'active', 'ends_at' => now()->addMonth()]);

        $seedToken = $user->createToken('seed');
        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'already-active-device'), $seedToken->accessToken, '127.0.0.1');

        $tokensBefore = $user->tokens()->count();

        $this->mockSocialiteProvider('google', ['id' => 'google-123', 'email' => 'jane@example.com']);

        $this->postJson('/api/v1/social/google', $this->payload(['device' => ['fingerprint' => 'a-second-new-device']]))
            ->assertStatus(403)
            ->assertJson(['errors' => ['code' => 'DEVICE_LIMIT_EXCEEDED']]);

        $this->assertSame($tokensBefore, $user->tokens()->count());
    }

    public function test_new_apple_user_falls_back_to_the_client_supplied_name(): void
    {
        // Apple's id_token never carries a name claim, unlike Google.
        $this->mockSocialiteProvider('apple', [
            'id' => 'apple-123',
            'email' => 'apple-user@example.com',
            'name' => null,
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/social/apple', $this->payload(['name' => 'Apple Person']))
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true);

        $user = User::where('email', 'apple-user@example.com')->firstOrFail();
        $this->assertSame('Apple Person', $user->name);
        $this->assertSame('apple-123', $user->apple_id);
    }

    public function test_browser_login_succeeds_without_a_device_payload(): void
    {
        $this->mockSocialiteProvider('google', [
            'id' => 'google-123',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]);

        $response = $this->withHeader('X-Client-Type', 'web')
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')
            ->postJson('/api/v1/social/google', ['token' => 'fake-google-access-token'])
            ->assertOk();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $device = UserDevice::where('user_id', $user->id)->firstOrFail();
        $this->assertStringContainsString('Chrome', (string) $device->name);

        $cookie = $response->getCookie('device_fp', decrypt: false);
        $this->assertNotNull($cookie);
    }
}
