<?php

namespace App\Jobs\Account;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Models\User;
use App\Services\Account\DeletionService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued counterpart to the admin panel's bulk "force delete" action (Users
 * and Guests index — see `executeBulkForceDelete()` on each) — dispatched
 * instead of running inline once the selection crosses
 * panel.bulk_account_action_queue_threshold. Each row is its own DB
 * transaction plus a Storage::delete() call for its avatar file
 * ({@see DeletionService::forceDeleteRecord()}), so a large selection
 * running synchronously in the request risks the web server timeout.
 *
 * Carries its own single "bulk" audit entry — forceDeleteRecord() logs
 * nothing itself — mirroring the synchronous action it replaces.
 */
class BulkForceDeleteAccounts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public array $userIds,
        public ActivityModule $module,
        public ?int $requestedBy = null,
    ) {}

    public function handle(DeletionService $deletions): void
    {
        ActivityLogger::log($this->module, ActivityAction::ForceDeleted, null, [
            'bulk' => true,
            'user_ids' => $this->userIds,
            'count' => count($this->userIds),
        ], causer: $this->resolveCauser(), context: ActivityContext::Queue);

        User::withTrashed()->whereIn('id', $this->userIds)->get()
            ->each(fn (User $user) => $deletions->forceDeleteRecord($user));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: BulkForceDeleteAccounts', [
            'job' => self::class,
            'module' => $this->module->value,
            'user_ids' => $this->userIds,
            'exception' => $exception,
        ]);
    }

    private function resolveCauser(): ?User
    {
        return $this->requestedBy ? User::find($this->requestedBy) : null;
    }
}
