<?php

namespace App\Jobs\Subscription;

use App\Enum\PaymentProvider;
use App\Services\Subscription\LifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin scheduled trigger for the subscription-status sweep. All logic lives in
 * {@see LifecycleService::syncStatuses()} — this job just passes
 * through whichever providers it was given, so adding a new provider once its
 * integration exists is a one-line change in routes/console.php, not here.
 */
class SyncSubscriptionStatuses implements ShouldQueue
{
    use Queueable;

    /**
     * The sweep is idempotent per subscription, so a failed run is not
     * retried — the next scheduled run catches any stragglers.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function handle(LifecycleService $lifecycle): void
    {
        $lifecycle->syncStatuses([PaymentProvider::Local]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: SyncSubscriptionStatuses', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
