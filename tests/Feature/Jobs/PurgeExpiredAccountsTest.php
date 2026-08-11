<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Account\PurgeExpiredAccounts;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PurgeExpiredAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sweep_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'account-deletion-purge');

        $this->assertNotNull($event, 'The purge sweep should be registered on the schedule.');
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_the_job_is_dispatched_when_the_schedule_runs(): void
    {
        Bus::fake();
        $this->travelTo(now()->startOfHour());

        $this->artisan('schedule:run')->assertSuccessful();

        Bus::assertDispatched(PurgeExpiredAccounts::class);
    }

    public function test_handle_purges_expired_app_accounts(): void
    {
        config(['panel.account_deletion_grace_hours' => 24]);

        $expired = User::factory()->pendingDeletion('user', now()->subHours(25))->create();
        $withinGrace = User::factory()->pendingDeletion('user', now()->subHour())->create();

        (new PurgeExpiredAccounts)->handle(app(DeletionService::class));

        $this->assertNull(User::withTrashed()->find($expired->id), 'Expired account should be purged');
        $this->assertNotNull($withinGrace->fresh(), 'Account within grace should survive');
    }
}
