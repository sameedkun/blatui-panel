<?php

namespace App\Jobs;

use App\Services\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin hourly trigger for the account-deletion sweep. All logic lives in
 * {@see AccountDeletionService::purgeExpired()} — this job just invokes it.
 */
class PurgeExpiredAccounts implements ShouldQueue
{
    use Queueable;

    /**
     * Purge is destructive and idempotent per account, so a failed sweep is not
     * retried — the next hourly run catches any stragglers.
     */
    public int $tries = 1;

    /**
     * The purge can take a while if there are many accounts to delete,
     * so we give it a generous timeout.
     */
    public int $timeout = 300;

    public function handle(AccountDeletionService $deletions): void
    {
        $deletions->purgeExpired();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PurgeExpiredAccounts', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
