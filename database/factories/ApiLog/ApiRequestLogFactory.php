<?php

namespace Database\Factories\ApiLog;

use App\Models\ApiLog\ApiRequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => 'req_'.Str::ulid(),
            'correlation_id' => 'cor_'.Str::ulid(),
            'method' => 'GET',
            'path' => '/api/v1/me',
            'route_uri' => 'api/v1/me',
            'route_name' => 'api.v1.me.show',
            'api_version' => 'v1',
            'status_code' => 200,
            'status_class' => 2,
            'duration_ms' => fake()->randomFloat(2, 5, 400),
            'memory_peak_kb' => fake()->numberBetween(8000, 40000),
            'db_query_count' => fake()->numberBetween(1, 12),
            'db_time_ms' => fake()->randomFloat(2, 0, 50),
            'request_size' => 0,
            'response_size' => fake()->numberBetween(100, 4000),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'client_type' => 'app',
            'sample_weight' => 1,
            'created_at' => now(),
        ];
    }

    public function status(int $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_code' => $code,
            'status_class' => intdiv($code, 100),
        ]);
    }

    public function route(string $method, string $routeUri): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => $method,
            'route_uri' => $routeUri,
            'path' => '/'.$routeUri,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'user_type' => $user->type->value,
        ]);
    }

    public function withException(): static
    {
        return $this->status(500)->state(fn (array $attributes): array => ['has_exception' => true]);
    }
}
