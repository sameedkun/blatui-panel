<?php

namespace App\Jobs;

use App\Enum\PaymentProvider;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Thin scheduled trigger for the subscription-status sweep. All logic lives in
 * {@see SubscriptionLifecycleService::syncStatuses()} — this job just passes
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

    /** @param  array<int, PaymentProvider>  $providers */
    public function __construct(public readonly array $providers = [PaymentProvider::Local]) {}

    public function handle(SubscriptionLifecycleService $lifecycle): void
    {
        $lifecycle->syncStatuses($this->providers);
    }
}
