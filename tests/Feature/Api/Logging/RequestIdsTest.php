<?php

namespace Tests\Feature\Api\Logging;

use App\Models\BlockedIp;
use App\Support\ApiLogs\RequestIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RequestIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_api_response_carries_a_request_id_and_a_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/plans')->assertOk();

        $this->assertMatchesRegularExpression('/^req_[0-9A-Z]{26}$/', $response->headers->get(RequestIds::REQUEST_ID_HEADER));
        $this->assertMatchesRegularExpression('/^cor_[0-9A-Z]{26}$/', $response->headers->get(RequestIds::CORRELATION_ID_HEADER));
    }

    public function test_request_ids_are_unique_per_request(): void
    {
        $first = $this->getJson('/api/v1/plans')->headers->get(RequestIds::REQUEST_ID_HEADER);
        $second = $this->getJson('/api/v1/plans')->headers->get(RequestIds::REQUEST_ID_HEADER);

        $this->assertNotSame($first, $second);
    }

    public function test_a_client_supplied_request_id_is_never_trusted(): void
    {
        $response = $this->withHeader(RequestIds::REQUEST_ID_HEADER, 'req_CLIENTCHOSEN0000000000000000')->getJson('/api/v1/plans');

        $this->assertNotSame('req_CLIENTCHOSEN0000000000000000', $response->headers->get(RequestIds::REQUEST_ID_HEADER));
    }

    public function test_a_well_formed_client_correlation_id_is_kept(): void
    {
        $this->withHeader(RequestIds::CORRELATION_ID_HEADER, 'checkout-flow_42')
            ->getJson('/api/v1/plans')
            ->assertHeader(RequestIds::CORRELATION_ID_HEADER, 'checkout-flow_42');
    }

    public function test_a_malformed_client_correlation_id_is_replaced(): void
    {
        foreach (['short', str_repeat('a', 65), 'has spaces in it', '<script>alert(1)</script>'] as $malformed) {
            $correlationId = $this->withHeader(RequestIds::CORRELATION_ID_HEADER, $malformed)
                ->getJson('/api/v1/plans')
                ->headers->get(RequestIds::CORRELATION_ID_HEADER);

            $this->assertMatchesRegularExpression('/^cor_[0-9A-Z]{26}$/', $correlationId);
        }
    }

    public function test_an_error_envelope_includes_the_request_id(): void
    {
        $response = $this->getJson('/api/v1/devices')->assertStatus(401);

        $response->assertJsonPath('request_id', $response->headers->get(RequestIds::REQUEST_ID_HEADER));
    }

    public function test_an_unmatched_api_route_still_gets_ids(): void
    {
        $response = $this->getJson('/api/v1/this-route-does-not-exist')->assertNotFound();

        $response->assertJsonPath('request_id', $response->headers->get(RequestIds::REQUEST_ID_HEADER));
    }

    public function test_a_blocked_ip_rejection_includes_the_request_id(): void
    {
        BlockedIp::factory()->create(['ip_address' => '127.0.0.1']);

        $response = $this->getJson('/api/v1/plans')->assertForbidden();

        $response->assertJsonPath('error', 'IP_BLOCKED');
        $response->assertJsonPath('request_id', $response->headers->get(RequestIds::REQUEST_ID_HEADER));
    }

    public function test_a_successful_response_body_is_left_untouched(): void
    {
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonMissingPath('request_id');
    }

    public function test_non_api_requests_get_no_ids(): void
    {
        $response = $this->get('/login');

        $this->assertFalse($response->headers->has(RequestIds::REQUEST_ID_HEADER));
        $this->assertFalse($response->headers->has(RequestIds::CORRELATION_ID_HEADER));
    }

    public function test_audit_entries_written_during_an_api_request_carry_its_ids(): void
    {
        $response = $this->withHeader(RequestIds::CORRELATION_ID_HEADER, 'signup-flow-0001')
            ->postJson('/api/v1/signup', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertCreated();

        $activity = Activity::query()->latest('id')->firstOrFail();

        $this->assertSame($response->headers->get(RequestIds::REQUEST_ID_HEADER), $activity->properties['request_id']);
        $this->assertSame('signup-flow-0001', $activity->properties['correlation_id']);
    }
}
