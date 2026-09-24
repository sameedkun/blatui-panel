<?php

namespace Database\Factories\ApiLog;

use App\Enum\ApiStatsPeriod;
use App\Models\ApiLog\ApiRequestStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestStat>
 */
class ApiRequestStatFactory extends Factory
{
    protected $model = ApiRequestStat::class;

    /**
     * Every request lands in the 100ms histogram bucket, so percentiles are predictable.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requests = fake()->numberBetween(1, 500);

        return [
            'period' => ApiStatsPeriod::Hour,
            'bucket' => now()->startOfHour()->subHour(),
            'method' => 'GET',
            'route_uri' => 'api/v1/me',
            'api_version' => 'v1',
            'status_code' => 200,
            'status_class' => 2,
            'requests' => $requests,
            'duration_sum_ms' => $requests * 80,
            'duration_min_ms' => 60,
            'duration_max_ms' => 100,
            'db_time_sum_ms' => $requests * 5,
            'request_bytes' => 0,
            'response_bytes' => $requests * 1000,
            'h_le_100' => $requests,
        ];
    }

    public function period(ApiStatsPeriod $period, mixed $bucket): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => $period,
            'bucket' => $bucket,
        ]);
    }
}
