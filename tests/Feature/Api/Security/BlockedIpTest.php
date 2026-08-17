<?php

namespace Tests\Feature\Api\Security;

use App\Jobs\Auth\PruneExpiredBlockedIps;
use App\Models\BlockedIp;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BlockedIpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test below hits a real-looking IP (RFC 5737 test-net addresses) via
        // withServerVariables() — EnsureDeviceIsValid's touch() would otherwise see
        // the IP change from registration and synchronously dispatch (QUEUE_CONNECTION
        // is `sync` in tests) a real ResolveDeviceLocation job that calls out to a
        // geo-IP provider. Faking the queue keeps this test suite offline.
        Queue::fake();
    }

    private function tokenFor(User $user): string
    {
        $newToken = $user->createToken('test');
        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-'.$user->id), $newToken->accessToken, '127.0.0.1');

        return $newToken->plainTextToken;
    }

    public function test_a_global_block_rejects_any_request_from_that_ip(): void
    {
        $user = User::factory()->app()->create();
        $token = $this->tokenFor($user);

        BlockedIp::factory()->global()->create(['ip_address' => '203.0.113.10']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertStatus(403)
            ->assertJson(['error' => 'IP_BLOCKED']);
    }

    public function test_a_per_user_block_rejects_only_that_user(): void
    {
        $blockedUser = User::factory()->app()->create();
        $otherUser = User::factory()->app()->create();

        $blockedToken = $this->tokenFor($blockedUser);
        $otherToken = $this->tokenFor($otherUser);

        BlockedIp::factory()->forUser($blockedUser)->create(['ip_address' => '203.0.113.20']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeader('Authorization', 'Bearer '.$blockedToken)
            ->getJson('/api/v1/devices')
            ->assertStatus(403)
            ->assertJson(['error' => 'IP_BLOCKED']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/v1/devices')
            ->assertOk();
    }

    public function test_an_expired_block_does_not_reject_the_request(): void
    {
        $user = User::factory()->app()->create();
        $token = $this->tokenFor($user);

        BlockedIp::factory()->global()->expired()->create(['ip_address' => '203.0.113.30']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertOk();
    }

    public function test_duplicate_global_block_on_the_same_ip_is_rejected_by_the_database(): void
    {
        BlockedIp::factory()->global()->create(['ip_address' => '203.0.113.40']);

        $this->expectException(QueryException::class);

        BlockedIp::factory()->global()->create(['ip_address' => '203.0.113.40']);
    }

    /** Deleting a block must take effect immediately, not after the cache TTL expires. */
    public function test_deleting_a_block_lifts_it_immediately_even_though_the_lookup_is_cached(): void
    {
        $user = User::factory()->app()->create();
        $token = $this->tokenFor($user);

        $block = BlockedIp::factory()->global()->create(['ip_address' => '203.0.113.50']);

        // Cache the "blocked" result.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertStatus(403);

        $block->delete();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertOk();
    }

    /**
     * PruneExpiredBlockedIps bulk-deletes via whereIn(...)->delete(), which bypasses
     * Eloquent model events entirely — proves that path still invalidates the cache
     * itself rather than relying on BlockedIp's create/update/delete hooks.
     */
    public function test_the_expired_prune_job_lifts_a_cached_block_immediately(): void
    {
        $user = User::factory()->app()->create();
        $token = $this->tokenFor($user);

        BlockedIp::factory()->global()->create([
            'ip_address' => '203.0.113.60',
            'expires_at' => now()->addSeconds(5),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertStatus(403);

        // Row expires and gets pruned, but well inside the 60s cache TTL — a stale
        // cache (if the prune job didn't invalidate it) would still say "blocked".
        $this->travel(10)->seconds();
        (new PruneExpiredBlockedIps)->handle();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertOk();
    }

    /**
     * The `database`/`file` cache stores don't support tagging — Cache::tags()
     * throws BadMethodCallException on them. This must never surface to a
     * request; the block check should still work (and stay enforced), even
     * though tag-based invalidation degrades to relying on the TTL.
     */
    public function test_a_block_is_still_enforced_on_a_cache_store_without_tag_support(): void
    {
        config(['cache.default' => 'database']);

        $user = User::factory()->app()->create();
        $token = $this->tokenFor($user);

        BlockedIp::factory()->global()->create(['ip_address' => '203.0.113.70']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.70'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/devices')
            ->assertStatus(403)
            ->assertJson(['error' => 'IP_BLOCKED']);
    }
}
