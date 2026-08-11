<?php

namespace Tests\Feature\Api\Policies;

use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private function policyWithActiveVersion(string $key, string $title, string $content, string $version = '1.0'): Policy
    {
        $policy = Policy::create(['key' => $key, 'title' => $title]);

        $policy->versions()->create([
            'version' => $version,
            'content' => $content,
            'is_active' => true,
            'published_at' => now(),
        ]);

        return $policy;
    }

    public function test_index_lists_key_and_title_without_content(): void
    {
        $this->policyWithActiveVersion('privacy', 'Privacy Policy', 'Full privacy text here.');

        $response = $this->getJson('/api/v1/policies')->assertOk();

        $response->assertJsonFragment(['key' => 'privacy', 'title' => 'Privacy Policy']);
        $this->assertArrayNotHasKey('content', $response->json('data.0'));
    }

    public function test_show_returns_the_active_versions_content(): void
    {
        $this->policyWithActiveVersion('terms', 'Terms of Service', 'Full terms text here.', '2.1');

        $this->getJson('/api/v1/policies/terms')
            ->assertOk()
            ->assertJsonPath('data.key', 'terms')
            ->assertJsonPath('data.version', '2.1')
            ->assertJsonPath('data.content', 'Full terms text here.');
    }

    public function test_show_404s_for_an_unknown_key(): void
    {
        $this->getJson('/api/v1/policies/does-not-exist')->assertStatus(404);
    }
}
