<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiExceptionRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unmatched_api_route_renders_the_json_envelope(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertJson(['status' => false]);
    }

    public function test_an_unmatched_non_api_route_renders_html(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertStatus(404);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    public function test_an_unauthenticated_api_request_renders_the_json_envelope(): void
    {
        $this->getJson('/api/v1/devices')
            ->assertStatus(401)
            ->assertJson(['status' => false, 'message' => 'Unauthenticated']);
    }
}
