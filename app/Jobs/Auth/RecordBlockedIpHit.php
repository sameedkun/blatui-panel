<?php

namespace App\Jobs\Auth;

use App\Http\Middleware\CheckBlockedIp;
use App\Models\BlockedIp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Increments hit counters off the request path — {@see CheckBlockedIp}
 * dispatches this instead of writing synchronously, since a block check runs
 * on every single API request. Re-runs the match query uncached (a global and
 * a per-user rule can both match the same IP, so every matching row gets a hit).
 */
class RecordBlockedIpHit implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $ip,
        private readonly ?int $userId,
    ) {}

    public function handle(): void
    {
        BlockedIp::query()
            ->where('ip_address', $this->ip)
            ->where(fn ($q) => $q->whereNull('user_id')->when($this->userId, fn ($q2) => $q2->orWhere('user_id', $this->userId)))
            ->active()
            ->get()
            ->each->recordHit();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: RecordBlockedIpHit', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
