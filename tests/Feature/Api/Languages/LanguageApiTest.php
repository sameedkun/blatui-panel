<?php

namespace Tests\Feature;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_languages_without_translations(): void
    {
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Language::factory()->inactive()->create(['code' => 'xx', 'name' => 'Retired']);

        $response = $this->getJson('/api/v1/languages')->assertOk();

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('en'));
        $this->assertFalse($codes->contains('xx'));
        $this->assertArrayNotHasKey('translations', $response->json('data.0'));
    }

    public function test_show_returns_full_detail_including_translations(): void
    {
        Language::factory()->create([
            'code' => 'fr',
            'name' => 'French',
            'translations' => ['hello' => 'Bonjour'],
        ]);

        $this->getJson('/api/v1/languages/fr')
            ->assertOk()
            ->assertJsonPath('data.code', 'fr')
            ->assertJsonPath('data.translations.hello', 'Bonjour');
    }

    public function test_show_404s_for_an_inactive_language(): void
    {
        Language::factory()->inactive()->create(['code' => 'xx']);

        $this->getJson('/api/v1/languages/xx')->assertStatus(404);
    }

    public function test_show_404s_for_an_unknown_code(): void
    {
        $this->getJson('/api/v1/languages/zz')->assertStatus(404);
    }
}
