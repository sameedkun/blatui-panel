<?php

namespace Tests\Feature\Api\Auth;

use App\Enum\DeviceType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'jane@example.com',
            'password' => 'a-real-password',
            'device' => [
                'fingerprint' => 'device-fingerprint-a',
                'name' => "Jane's iPhone",
                'platform' => 'ios',
                'type' => 'mobile',
            ],
        ], $overrides);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->app()->create(array_merge([
            'email' => 'jane@example.com',
            'password' => 'a-real-password',
        ], $attributes));
    }

    public function test_login_succeeds_registers_the_device_and_returns_a_token(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/login', $this->payload())
            ->assertOk()
            ->assertJson(['status' => true]);

        $device = UserDevice::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(hash('sha256', 'device-fingerprint-a'), $device->device_fingerprint);
        $this->assertNotNull($device->token_id);

        $token = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertOk();

        $this->assertNotNull($user->fresh()->last_login);

        Activity::where('subject_id', $user->id)->where('event', 'login')->firstOrFail();
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/login', $this->payload(['password' => 'wrong-password']))
            ->assertStatus(422)
            ->assertJson(['status' => false])
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_an_unknown_email_with_the_same_generic_message(): void
    {
        $this->postJson('/api/v1/login', $this->payload(['email' => 'nobody@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_staff_and_guest_accounts(): void
    {
        User::factory()->create(['email' => 'staff@example.com', 'password' => 'a-real-password', 'type' => 'staff']);
        User::factory()->guest()->create(['email' => 'guest@example.com', 'password' => 'a-real-password']);

        $this->postJson('/api/v1/login', $this->payload(['email' => 'staff@example.com']))
            ->assertStatus(422);

        $this->postJson('/api/v1/login', $this->payload(['email' => 'guest@example.com']))
            ->assertStatus(422);
    }

    public function test_login_rejects_a_banned_account_without_issuing_a_token(): void
    {
        $user = $this->makeUser(['banned_at' => now(), 'ban_reason' => 'Fraudulent activity']);

        $this->postJson('/api/v1/login', $this->payload())
            ->assertStatus(403)
            ->assertJson(['status' => false, 'errors' => ['code' => 'ACCOUNT_BANNED']]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_rejects_a_soft_deleted_account(): void
    {
        $user = $this->makeUser();
        $user->delete();

        $this->postJson('/api/v1/login', $this->payload())
            ->assertStatus(410);
    }

    public function test_login_surfaces_pending_deletion_state_but_still_succeeds(): void
    {
        $user = $this->makeUser([
            'deletion_requested_at' => now(),
            'deletion_requested_by' => 'user',
        ]);

        $this->postJson('/api/v1/login', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.user.pending_deletion.can_cancel', true);

        $this->assertTrue($user->fresh()->isPendingDeletion());
    }

    public function test_login_rejects_a_blocked_device_without_issuing_a_working_token(): void
    {
        $user = $this->makeUser();
        [$device] = $this->registerDevice($user, 'device-fingerprint-a');
        app(DeviceService::class)->block($device, 'Reported stolen.');

        $tokensBefore = $user->tokens()->count();

        $this->postJson('/api/v1/login', $this->payload())
            ->assertStatus(403)
            ->assertJson(['errors' => ['code' => 'DEVICE_BLOCKED']]);

        $this->assertSame($tokensBefore, $user->tokens()->count());
    }

    public function test_login_rejects_past_the_device_limit_without_issuing_a_working_token(): void
    {
        $user = $this->makeUser();

        // Pinned via an explicit subscription rather than relying on
        // config('panel.features.device_limit.default') — that default is a
        // product/pricing decision that can change independently of this
        // security behavior.
        $plan = Plan::factory()->create(['features' => ['device_limit' => 1]]);
        Subscription::factory()->for($user)->for($plan)->create([
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);

        $this->registerDevice($user, 'already-active-device');

        $tokensBefore = $user->tokens()->count();

        $this->postJson('/api/v1/login', $this->payload(['device' => [
            'fingerprint' => 'a-second-new-device',
        ]]))
            ->assertStatus(403)
            ->assertJson(['errors' => ['code' => 'DEVICE_LIMIT_EXCEEDED']]);

        $this->assertSame($tokensBefore, $user->tokens()->count());
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', $this->payload(['password' => 'wrong-password']))
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/login', $this->payload())
            ->assertStatus(429)
            ->assertJson(['status' => false]);
    }

    public function test_browser_login_succeeds_without_a_device_payload_and_issues_a_device_cookie(): void
    {
        $user = $this->makeUser();

        $response = $this->withHeader('X-Client-Type', 'web')
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')
            ->postJson('/api/v1/login', ['email' => 'jane@example.com', 'password' => 'a-real-password'])
            ->assertOk();

        $device = UserDevice::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(DeviceType::Web, $device->device_type);
        $this->assertStringContainsString('Chrome', (string) $device->name);

        $cookie = $response->getCookie('device_fp', decrypt: false);
        $this->assertNotNull($cookie);
        $this->assertSame(hash('sha256', $cookie->getValue()), $device->device_fingerprint);
    }

    public function test_browser_login_reuses_the_same_device_on_a_repeat_visit_via_the_cookie(): void
    {
        $user = $this->makeUser();
        $headers = ['X-Client-Type' => 'web', 'User-Agent' => 'Mozilla/5.0 Chrome/120.0'];
        $credentials = ['email' => 'jane@example.com', 'password' => 'a-real-password'];

        $first = $this->withHeaders($headers)->postJson('/api/v1/login', $credentials)->assertOk();
        $fingerprint = $first->getCookie('device_fp', decrypt: false)->getValue();

        // withCredentials() — postJson()'s cookie jar is empty by default
        // (mirrors a real fetch() without credentials: 'include'), so the
        // cookie set above would otherwise be silently dropped on send.
        // withUnencryptedCookie, not withCookie — the api middleware group
        // carries no EncryptCookies, so device_fp round-trips as a plain
        // value in production; withCookie() would simulate Laravel's normal
        // encrypted-cookie convention instead, which nothing here decrypts.
        $this->withCredentials()
            ->withHeaders($headers)
            ->withUnencryptedCookie('device_fp', $fingerprint)
            ->postJson('/api/v1/login', $credentials)
            ->assertOk();

        $this->assertSame(1, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_browser_device_limit_is_separate_from_the_app_device_limit(): void
    {
        $user = $this->makeUser();
        $this->registerDevice($user, 'phone-fingerprint'); // fills the default app limit of 1

        // A browser login must still succeed — it doesn't compete with the app bucket.
        $this->withHeader('X-Client-Type', 'web')
            ->postJson('/api/v1/login', ['email' => 'jane@example.com', 'password' => 'a-real-password'])
            ->assertOk();

        $this->assertSame(2, UserDevice::where('user_id', $user->id)->active()->count());
    }

    /**
     * @return array{0: UserDevice}
     */
    private function registerDevice(User $user, string $fingerprint): array
    {
        $token = $user->createToken('seed');
        $device = app(DeviceService::class)->register($user, new DeviceData(fingerprint: $fingerprint), $token->accessToken, '127.0.0.1');

        return [$device];
    }
}
