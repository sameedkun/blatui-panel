<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Account\BulkPurgeAccounts;
use App\Models\BlockedIp;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BulkPurgeAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_instant_purges_every_app_account_and_cleans_up_related_data(): void
    {
        Storage::fake();

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $path = UploadedFile::fake()->image('avatar.jpg')->store('avatars');
        $userA = User::factory()->app()->create(['avatar' => $path]);
        $userB = User::factory()->app()->create();
        BlockedIp::factory()->forUser($userA)->create();

        (new BulkPurgeAccounts([$userA->id, $userB->id], 'app', 'Bulk cleanup', $admin->id))
            ->handle(app(DeletionService::class));

        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userB->id]);
        Storage::assertMissing($path);
        $this->assertDatabaseMissing('blocked_ips', ['user_id' => $userA->id]);

        // Each row logs its own Purged activity (via DeletionService::purge()) — no bulk wrapper.
        $rows = Activity::where('event', 'purged')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame($admin->id, $row->causer_id);
            $this->assertSame('queue', $row->properties['context']);
            $this->assertSame('admin_instant', $row->properties['initiated_by']);
        }
    }

    public function test_handle_purges_every_selected_guest(): void
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $guestA = User::factory()->guest()->create(['banned_at' => null]);
        $guestB = User::factory()->guest()->create(['banned_at' => null]);

        (new BulkPurgeAccounts([$guestA->id, $guestB->id], 'guest', null, $admin->id))
            ->handle(app(DeletionService::class));

        $this->assertDatabaseMissing('users', ['id' => $guestA->id]);
        $this->assertDatabaseMissing('users', ['id' => $guestB->id]);

        $rows = Activity::where('event', 'purged')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn (Activity $row): bool => $row->properties['module'] === 'guest'));
    }

    public function test_handle_ignores_ids_outside_the_requested_type(): void
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        // Guest id passed to the 'app' job — the appUsers() scope must exclude it.
        (new BulkPurgeAccounts([$guest->id], 'app', null, $admin->id))
            ->handle(app(DeletionService::class));

        $this->assertNotNull($guest->fresh());
    }
}
