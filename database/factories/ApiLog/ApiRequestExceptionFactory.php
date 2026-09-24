<?php

namespace Database\Factories\ApiLog;

use App\Models\ApiLog\ApiRequestException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<ApiRequestException>
 */
class ApiRequestExceptionFactory extends Factory
{
    protected $model = ApiRequestException::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = 'app/Http/Controllers/Api/V1/ProfileController.php';
        $line = fake()->numberBetween(10, 200);

        return [
            'request_id' => 'req_'.Str::ulid(),
            'correlation_id' => 'cor_'.Str::ulid(),
            'method' => 'GET',
            'route_uri' => 'api/v1/me',
            'status_code' => 500,
            'class' => RuntimeException::class,
            'message' => fake()->sentence(),
            'file' => $file,
            'line' => $line,
            'trace' => [],
            'previous' => [],
            'fingerprint' => sha1(RuntimeException::class.'|'.$file.'|'.$line),
            'created_at' => now(),
        ];
    }
}
