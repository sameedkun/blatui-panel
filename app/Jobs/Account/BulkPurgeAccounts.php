<?php

namespace App\Jobs\Account;

use App\Enum\ActivityContext;
use App\Models\User;
use App\Services\Account\DeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued counterpart to the admin panel's bulk "instant purge" (app users —
 * Users/Index::executeBulkInstantPurge()) and "delete" (guests, which is
 * instant/permanent — Guests/Index::executeBulkDelete()) actions —
 * dispatched instead of running inline once the selection crosses
 * panel.bulk_account_action_queue_threshold. Each row already logs its own
 * Purged activity via {@see DeletionService::purge()}, so this job carries
 * no bulk wrapper log of its own — same as the synchronous action it
 * replaces.
 */
class BulkPurgeAccounts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array<int, int>  $userIds
     * @param  'app'|'guest'  $type
     */
    public function __construct(
        public array $userIds,
        public string $type,
        public ?string $reason = null,
        public ?int $requestedBy = null,
    ) {}

    public function handle(DeletionService $deletions): void
    {
        $causer = $this->requestedBy ? User::find($this->requestedBy) : null;

        $query = $this->type === 'guest' ? User::query()->guests() : User::query()->appUsers();

        $query->withTrashed()->whereIn('id', $this->userIds)->get()
            ->each(function (User $user) use ($deletions, $causer): void {
                $this->type === 'guest'
                    ? $deletions->purgeGuestByAdmin($user, $causer, ActivityContext::Queue)
                    : $deletions->instantPurgeByAdmin($user, $this->reason, $causer, ActivityContext::Queue);
            });
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: BulkPurgeAccounts', [
            'job' => self::class,
            'type' => $this->type,
            'user_ids' => $this->userIds,
            'exception' => $exception,
        ]);
    }
}
