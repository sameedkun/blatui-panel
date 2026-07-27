<?php

namespace App\Jobs;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Models\BlockedIp;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily sweep deleting every blocked_ips row whose expires_at has passed.
 * Permanent blocks (expires_at null) are never touched. A single row-delete
 * mutation with no per-row side effects (unlike ticket purges, which clean up
 * an attachments folder per row), so this stays a thin job rather than
 * delegating to a dedicated service class.
 */
class PruneExpiredBlockedIps implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(): void
    {
        $expired = BlockedIp::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get(['id', 'ip_address']);

        if ($expired->isEmpty()) {
            return;
        }

        BlockedIp::whereIn('id', $expired->pluck('id'))->delete();
        BlockedIp::forgetCache($expired->pluck('ip_address'));

        ActivityLogger::log(ActivityModule::BlockedIp, ActivityAction::Deleted, null, [
            'bulk' => true,
            'type' => 'blocked_ip_expired_purged',
            'blocked_ip_ids' => $expired->pluck('id')->all(),
            'count' => $expired->count(),
        ], causer: null, context: ActivityContext::Scheduler);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PruneExpiredBlockedIps', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
