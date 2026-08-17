<?php

namespace Tests\Feature\Jobs;

use App\Enum\ActivityModule;
use App\Jobs\Account\BulkForceDeleteAccounts;
use App\Models\BlockedIp;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BulkForceDeleteAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_force_deletes_every_account_and_cleans_up_related_data(): void
    {
        Storage::fake();

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $pathA = UploadedFile::fake()->image('a.jpg')->store('avatars');
        $pathB = UploadedFile::fake()->image('b.jpg')->store('avatars');
        $userA = User::factory()->app()->create(['avatar' => $pathA]);
        $userB = User::factory()->app()->create(['avatar' => $pathB]);
        BlockedIp::factory()->forUser($userA)->create();
        $userA->delete();
        $userB->delete();

        (new BulkForceDeleteAccounts([$userA->id, $userB->id], ActivityModule::User, $admin->id))
            ->handle(app(DeletionService::class));

        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userB->id]);
        Storage::assertMissing($pathA);
        Storage::assertMissing($pathB);
        $this->assertDatabaseMissing('blocked_ips', ['user_id' => $userA->id]);
    }

    public function test_handle_writes_a_single_bulk_activity_log_row_attributed_to_the_requesting_admin(): void
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();
        $userA->delete();
        $userB->delete();

        (new BulkForceDeleteAccounts([$userA->id, $userB->id], ActivityModule::User, $admin->id))
            ->handle(app(DeletionService::class));

        $row = Activity::where('event', 'force_deleted')->whereNull('subject_id')->latest('id')->firstOrFail();
        $this->assertTrue($row->properties['bulk']);
        $this->assertSame(2, $row->properties['count']);
        $this->assertSame('user', $row->properties['module']);
        $this->assertSame('queue', $row->properties['context']);
        $this->assertSame($admin->id, $row->causer_id);
    }

    public function test_handle_supports_the_guest_module(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();

        (new BulkForceDeleteAccounts([$guest->id], ActivityModule::Guest, null))
            ->handle(app(DeletionService::class));

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);

        $row = Activity::where('event', 'force_deleted')->whereNull('subject_id')->latest('id')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
        $this->assertNull($row->causer_id);
    }
}
