<?php

namespace Tests\Unit\Support\ApiLogs;

use App\Support\ApiLogs\SamplingPolicy;
use Tests\TestCase;

class SamplingPolicyTest extends TestCase
{
    public function test_everything_is_kept_at_weight_one_by_default(): void
    {
        $policy = new SamplingPolicy(fn (): int => 100);

        foreach ([2, 3, 4, 5] as $statusClass) {
            $this->assertSame(1, $policy->weight($statusClass));
        }
    }

    public function test_a_class_override_falls_back_to_the_global_rate_when_unset(): void
    {
        config(['api_logs.sampling.rate' => 50, 'api_logs.sampling.rates' => [2 => 10, 3 => null, 4 => '', 5 => 100]]);

        $policy = new SamplingPolicy;

        $this->assertSame(10, $policy->rateFor(2));
        $this->assertSame(50, $policy->rateFor(3));
        $this->assertSame(50, $policy->rateFor(4));
        $this->assertSame(100, $policy->rateFor(5));
    }

    public function test_a_kept_sampled_row_carries_the_inverse_rate_as_its_weight(): void
    {
        config(['api_logs.sampling.rates.2' => 10]);

        $this->assertSame(10, (new SamplingPolicy(fn (): int => 7))->weight(2));
        $this->assertNull((new SamplingPolicy(fn (): int => 11))->weight(2));
    }

    public function test_forced_requests_are_always_kept_at_weight_one(): void
    {
        config(['api_logs.sampling.rate' => 0]);

        $policy = new SamplingPolicy(fn (): int => 100);

        $this->assertNull($policy->weight(5));
        $this->assertSame(1, $policy->weight(5, forceKeep: true));
    }

    public function test_rates_are_clamped_to_0_100(): void
    {
        config(['api_logs.sampling.rates' => [2 => 250, 3 => -5]]);

        $policy = new SamplingPolicy;

        $this->assertSame(100, $policy->rateFor(2));
        $this->assertSame(0, $policy->rateFor(3));
    }
}
