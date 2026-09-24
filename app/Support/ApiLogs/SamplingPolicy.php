<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

/**
 * Decides whether a finished request is kept in the raw log, per
 * config('api_logs.sampling'): a global percentage with optional per-status-
 * class overrides. A kept row's sample weight (100 / rate) is what
 * aggregation sums instead of counting rows, so stats stay an unbiased
 * estimate of real traffic when, say, only 10% of 2xx requests are stored.
 *
 * Forced requests (an exception, an error code, a slow request) are always
 * kept at weight 1 — every failure stays searchable by request id no matter
 * how aggressive sampling gets.
 */
class SamplingPolicy
{
    /**
     * @param  (callable(): int)|null  $roll  Returns 1-100; injectable for tests.
     */
    public function __construct(private readonly mixed $roll = null) {}

    /** The row's sample weight, or null when the request should be dropped. */
    public function weight(int $statusClass, bool $forceKeep = false): ?int
    {
        if ($forceKeep) {
            return 1;
        }

        $rate = $this->rateFor($statusClass);

        if ($rate >= 100) {
            return 1;
        }

        if ($rate <= 0 || $this->roll() > $rate) {
            return null;
        }

        return (int) round(100 / $rate);
    }

    public function rateFor(int $statusClass): int
    {
        $override = config("api_logs.sampling.rates.{$statusClass}");
        $rate = $override === null || $override === '' ? config('api_logs.sampling.rate', 100) : $override;

        return max(0, min(100, (int) $rate));
    }

    private function roll(): int
    {
        return $this->roll !== null ? (int) ($this->roll)() : random_int(1, 100);
    }
}
