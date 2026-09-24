<?php

namespace Tests\Feature\Api\Logging;

use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestPayload;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Device\DeviceService;
use App\Support\ApiLogs\ApiLogBuffer;
use App\Support\ApiLogs\RequestIds;
use App\Support\ApiLogs\Sanitizer;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string, 2: int}
     */
    private function authenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->app()->create($attributes);
        $token = $user->createToken('device');

        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-a'), $token->accessToken, '127.0.0.1');

        return [$user, $token->plainTextToken, $token->accessToken->id];
    }

    private function logFor(TestResponse $response): ApiRequestLog
    {
        return ApiRequestLog::where('request_id', $response->headers->get(RequestIds::REQUEST_ID_HEADER))->firstOrFail();
    }

    public function test_an_authenticated_request_is_logged_with_its_user_token_and_device(): void
    {
        [$user, $token, $tokenId] = $this->authenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader(RequestIds::CORRELATION_ID_HEADER, 'profile-check-01')
            ->getJson('/api/v1/me')
            ->assertOk();

        $log = $this->logFor($response);

        $this->assertSame('GET', $log->method);
        $this->assertSame('/api/v1/me', $log->path);
        $this->assertSame('api/v1/me', $log->route_uri);
        $this->assertSame('v1', $log->api_version);
        $this->assertSame(200, $log->status_code);
        $this->assertSame(2, $log->status_class);
        $this->assertSame('profile-check-01', $log->correlation_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('app', $log->user_type);
        $this->assertSame($tokenId, $log->token_id);
        $this->assertSame(UserDevice::where('user_id', $user->id)->value('id'), $log->device_id);
        $this->assertSame(1, $log->sample_weight);
        $this->assertGreaterThan(0, $log->db_query_count);
        $this->assertArrayHasKey('response_ready', $log->timings);
        $this->assertArrayHasKey('route_matched', $log->timings);
    }

    public function test_a_plain_successful_read_stores_no_payload_row(): void
    {
        [, $token] = $this->authenticatedUser();

        $log = $this->logFor($this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk());

        $this->assertFalse($log->has_payload);
        $this->assertSame(0, ApiRequestPayload::count());
    }

    public function test_successful_reads_are_captured_when_configured(): void
    {
        config(['api_logs.capture.successful_reads' => true]);
        [, $token] = $this->authenticatedUser();

        $log = $this->logFor($this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk());

        $this->assertTrue($log->has_payload);
        $this->assertNotNull($log->payload);
    }

    public function test_a_write_stores_a_sanitized_payload(): void
    {
        [$user, $token, $tokenId] = $this->authenticatedUser(['name' => 'Old Name', 'email' => 'jane@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['name' => 'Sameed Chan'])
            ->assertOk();

        $payload = $this->logFor($response)->payload;

        $this->assertSame('S***** C***', $payload->request_body['name']);
        $this->assertSame("Bearer {$tokenId}|".Sanitizer::REDACTED, $payload->request_headers['authorization']);
        $this->assertSame('S***** C***', $payload->response_body['data']['user']['name']);
        $this->assertSame('j***@example.com', $payload->response_body['data']['user']['email']);
        $this->assertStringNotContainsString(explode('|', $token)[1], json_encode($payload->request_headers));
    }

    public function test_secrets_never_reach_the_stored_payload(): void
    {
        User::factory()->app()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password-123',
        ])->assertStatus(422);

        $log = $this->logFor($response);

        $this->assertSame(Sanitizer::REDACTED, $log->payload->request_body['password']);
        $this->assertSame('j***@example.com', $log->payload->request_body['email']);
        $this->assertStringNotContainsString('wrong-password-123', json_encode($log->payload->request_body));
    }

    public function test_query_strings_are_stored_sanitized(): void
    {
        $response = $this->getJson('/api/v1/plans?locale=en&access_token=abc123secret')->assertOk();

        $payload = $this->logFor($response)->payload;

        $this->assertSame(['locale' => 'en', 'access_token' => Sanitizer::REDACTED], $payload->query);
    }

    public function test_secret_route_parameters_are_redacted_from_the_path(): void
    {
        $user = User::factory()->app()->unverified()->create();

        $response = $this->getJson("/api/v1/email/verify/{$user->external_id}/secret-hash-value");

        $log = $this->logFor($response);

        $this->assertStringNotContainsString('secret-hash-value', $log->path);
        $this->assertStringEndsWith('/'.Sanitizer::REDACTED, $log->path);
    }

    public function test_an_unmatched_route_is_logged(): void
    {
        $log = $this->logFor($this->getJson('/api/v1/this-route-does-not-exist')->assertNotFound());

        $this->assertSame(ApiRequestLog::UNMATCHED_ROUTE, $log->route_uri);
        $this->assertSame(404, $log->status_code);
        $this->assertSame(4, $log->status_class);
        $this->assertTrue($log->has_payload);
    }

    public function test_a_machine_readable_error_code_is_extracted(): void
    {
        BlockedIp::factory()->create(['ip_address' => '127.0.0.1']);

        $log = $this->logFor($this->getJson('/api/v1/plans')->assertForbidden());

        $this->assertSame('IP_BLOCKED', $log->error_code);
    }

    public function test_an_exception_is_captured_and_linked_by_request_id(): void
    {
        Route::middleware('api')->get('api/testing/explode', fn () => throw new RuntimeException('Checkout failed for jane@example.com'));

        $response = $this->getJson('/api/testing/explode')->assertStatus(500);
        $log = $this->logFor($response);
        $exception = ApiRequestException::where('request_id', $log->request_id)->firstOrFail();

        $this->assertTrue($log->has_exception);
        $this->assertSame(RuntimeException::class, $exception->class);
        $this->assertSame('Checkout failed for j***@example.com', $exception->message);
        $this->assertSame('tests/Feature/Api/Logging/ApiRequestLoggingTest.php', $exception->file);
        $this->assertSame(500, $exception->status_code);
        $this->assertSame($log->correlation_id, $exception->correlation_id);
        $this->assertSame(sha1(RuntimeException::class.'|'.$exception->file.'|'.$exception->line), $exception->fingerprint);
        $this->assertNotEmpty($exception->trace);
        $this->assertArrayNotHasKey('args', $exception->trace[0]);
        $this->assertSame(['file', 'line', 'call', 'app'], array_keys($exception->trace[0]));
    }

    public function test_non_api_requests_are_not_logged(): void
    {
        $this->get('/login');

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_excluded_paths_are_not_logged(): void
    {
        config(['api_logs.exclude_paths' => ['api/v1/plans*']]);

        $this->getJson('/api/v1/plans')->assertOk();

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_logging_can_be_disabled(): void
    {
        config(['api_logs.enabled' => false]);

        $this->getJson('/api/v1/plans')->assertOk();

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_sampled_out_successes_are_dropped_while_errors_are_always_kept(): void
    {
        config(['api_logs.sampling.rates' => [2 => 0, 3 => null, 4 => null, 5 => null]]);

        $this->getJson('/api/v1/plans')->assertOk();
        $this->getJson('/api/v1/this-route-does-not-exist')->assertNotFound();

        $this->assertSame([404], ApiRequestLog::pluck('status_code')->all());
    }

    public function test_a_logging_failure_never_breaks_the_request(): void
    {
        $this->app->instance(ApiLogBuffer::class, new class implements ApiLogBuffer
        {
            public function push(array $record): void
            {
                throw new RuntimeException('Redis is down');
            }

            public function pop(int $count): array
            {
                return [];
            }
        });

        $this->getJson('/api/v1/plans')->assertOk();
    }

    public function test_each_request_in_a_sequence_is_logged_independently(): void
    {
        Route::middleware('api')->get('api/testing/explode', fn () => throw new RuntimeException('boom'));

        $this->getJson('/api/testing/explode')->assertStatus(500);
        $this->getJson('/api/v1/plans')->assertOk();

        $this->assertSame(2, ApiRequestLog::count());
        $this->assertSame(1, ApiRequestLog::where('has_exception', true)->count());
        $this->assertSame(1, ApiRequestException::count());
    }
}
