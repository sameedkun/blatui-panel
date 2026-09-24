<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class ApiLogsLocalizationTest extends TestCase
{
    public function test_english_and_turkish_api_log_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('api_logs', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('api_logs', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_navigation_and_permission_labels_exist_in_both_locales(): void
    {
        foreach (['en', 'tr'] as $locale) {
            foreach (['navigation.modules.api_logs', 'navigation.modules.api_log_requests', 'navigation.modules.api_log_analytics', 'roles.permissions.scopes.requests', 'roles.permissions.scopes.analytics'] as $key) {
                $this->assertTrue(Lang::has($key, $locale, false), "Missing [{$key}] for [{$locale}].");
            }
        }
    }
}
