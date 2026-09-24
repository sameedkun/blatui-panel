<?php

namespace Tests\Feature\Admin\ApiLogs;

use App\Livewire\Admin\Administration\ApiLogs\Requests\Index;
use App\Livewire\Admin\Administration\ApiLogs\Requests\Show;
use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestPayload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiLogRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWith(array $permissions): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    private function logWithPayload(array $attributes = []): ApiRequestLog
    {
        $log = ApiRequestLog::factory()->route('POST', 'api/v1/tickets')->status(422)->create(['has_payload' => true, ...$attributes]);

        ApiRequestPayload::create([
            'request_id' => $log->request_id,
            'request_headers' => ['authorization' => 'Bearer 7|[REDACTED]', 'cookie' => '[REDACTED]', 'accept' => 'application/json'],
            'query' => ['page' => '2'],
            'request_body' => ['subject' => 'Help', 'password' => '[REDACTED]'],
            'response_headers' => ['content-type' => 'application/json'],
            'response_body' => ['status' => false, 'message' => 'Validation failed'],
            'created_at' => $log->created_at,
        ]);

        return $log;
    }

    public function test_the_requests_page_is_forbidden_without_permission(): void
    {
        $this->actingAsAdminWith(['panel.access-admin']);

        $this->get(route('admin.api-logs.requests.index'))->assertForbidden();
        $this->get(route('admin.api-logs.analytics'))->assertForbidden();
        $this->get(route('admin.api-logs.index'))->assertForbidden();
    }

    public function test_the_module_view_permission_grants_both_pages(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.view']);

        $this->get(route('admin.api-logs.requests.index'))->assertOk();
        $this->get(route('admin.api-logs.analytics'))->assertOk();
    }

    public function test_the_module_index_redirects_to_the_first_page_the_viewer_can_open(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.analytics.view']);

        $this->get(route('admin.api-logs.index'))->assertRedirect(route('admin.api-logs.analytics'));
    }

    public function test_the_list_defaults_to_the_last_24_hours(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $recent = ApiRequestLog::factory()->create(['created_at' => now()->subHours(2)]);
        ApiRequestLog::factory()->create(['created_at' => now()->subDays(3)]);

        Livewire::test(Index::class)
            ->assertViewHas('requests', fn ($requests): bool => $requests->pluck('request_id')->all() === [$recent->request_id])
            ->call('setFilter', 'period', '7d')
            ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 2);
    }

    public function test_filters_narrow_the_list(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $failed = ApiRequestLog::factory()->route('POST', 'api/v1/tickets')->status(500)->create(['has_exception' => true, 'error_code' => 'BOOM']);
        ApiRequestLog::factory()->route('GET', 'api/v1/me')->status(200)->create();

        foreach ([
            ['status_class', ['5']],
            ['method', ['POST']],
            ['route', 'api/v1/tickets'],
            ['has_exception', '1'],
            ['error_code', 'BOOM'],
            ['status_code', '500'],
        ] as [$key, $value]) {
            Livewire::test(Index::class)
                ->call('setFilter', $key, $value)
                ->assertViewHas('requests', fn ($requests): bool => $requests->pluck('request_id')->all() === [$failed->request_id]);
        }
    }

    public function test_search_matches_correlation_ids_paths_and_ips(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $target = ApiRequestLog::factory()->create(['correlation_id' => 'checkout-0001', 'ip' => '198.51.100.7', 'path' => '/api/v1/tickets/42']);
        ApiRequestLog::factory()->create();

        foreach (['checkout-0001', '198.51.100.7', 'tickets/42'] as $term) {
            Livewire::test(Index::class)
                ->set('search', $term)
                ->assertViewHas('requests', fn ($requests): bool => $requests->pluck('request_id')->all() === [$target->request_id]);
        }
    }

    public function test_searching_an_exact_request_id_opens_that_request(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $log = ApiRequestLog::factory()->create();

        Livewire::test(Index::class)
            ->set('search', $log->request_id)
            ->assertRedirect(route('admin.api-logs.requests.show', $log->request_id));
    }

    public function test_deep_links_prefill_filters_and_widen_the_period(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $older = ApiRequestLog::factory()->create(['correlation_id' => 'signup-flow-9', 'created_at' => now()->subDays(2)]);
        ApiRequestLog::factory()->create();

        Livewire::withQueryParams(['correlation' => 'signup-flow-9'])
            ->test(Index::class)
            ->assertSet('filters.period', '7d')
            ->assertViewHas('requests', fn ($requests): bool => $requests->pluck('request_id')->all() === [$older->request_id]);
    }

    public function test_resetting_filters_restores_the_default_window(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);

        Livewire::test(Index::class)
            ->call('setFilter', 'period', '7d')
            ->call('setFilter', 'ip', '203.0.113.9')
            ->call('resetFilters')
            ->assertSet('filters.period', '24h')
            ->assertSet('filters.ip', '');
    }

    public function test_only_whitelisted_columns_are_sortable(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);

        Livewire::test(Index::class)
            ->call('sort', 'duration_ms')
            ->assertSet('sortBy', 'duration_ms')
            ->call('sort', 'path; drop table users')
            ->assertSet('sortBy', 'duration_ms');
    }

    public function test_the_detail_page_opens_by_request_id(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $log = ApiRequestLog::factory()->create();

        $this->get(route('admin.api-logs.requests.show', $log->request_id))->assertOk();
        $this->get(route('admin.api-logs.requests.show', 'req_01ZZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();
        $this->get('/api-logs/requests/not-a-request-id')->assertNotFound();
    }

    public function test_headers_and_bodies_need_the_manage_permission(): void
    {
        $log = $this->logWithPayload();

        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);

        Livewire::test(Show::class, ['requestId' => $log->request_id])
            ->call('selectTab', 'request')
            ->assertSet('tab', '')
            ->assertViewHas('tabs', fn (array $tabs): bool => array_keys($tabs) === ['overview', 'user', 'timeline']);

        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view', 'api_logs.requests.manage']);

        Livewire::test(Show::class, ['requestId' => $log->request_id])
            ->call('selectTab', 'request')
            ->assertSet('tab', 'request')
            ->assertViewHas('tabs', fn (array $tabs): bool => array_keys($tabs) === ['overview', 'request', 'response', 'user', 'timeline']);
    }

    public function test_the_curl_command_is_rebuilt_from_the_sanitized_log(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view', 'api_logs.requests.manage']);
        $log = $this->logWithPayload(['path' => '/api/v1/tickets']);

        $curl = Livewire::test(Show::class, ['requestId' => $log->request_id])
            ->call('selectTab', 'request')
            ->instance()
            ->activeTabData()['curl'];

        $this->assertStringStartsWith("curl -X POST '".rtrim(config('app.url'), '/')."/api/v1/tickets?page=2'", $curl);
        $this->assertStringContainsString("-H 'authorization: Bearer 7|[REDACTED]'", $curl);
        $this->assertStringNotContainsString('cookie', $curl);
        $this->assertStringContainsString('"password":"[REDACTED]"', $curl);
    }

    public function test_the_exceptions_tab_appears_only_when_the_request_raised_one(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $log = ApiRequestLog::factory()->withException()->create();
        $exception = ApiRequestException::factory()->create(['request_id' => $log->request_id]);
        $sibling = ApiRequestException::factory()->create(['fingerprint' => $exception->fingerprint]);

        $component = Livewire::test(Show::class, ['requestId' => $log->request_id])
            ->call('selectTab', 'exceptions')
            ->assertSet('tab', 'exceptions');

        $occurrences = $component->instance()->activeTabData()['occurrences'];
        $this->assertSame([$sibling->request_id], $occurrences[$exception->fingerprint]->pluck('request_id')->all());
    }

    public function test_a_request_whose_raw_log_expired_still_opens_from_its_exception(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $exception = ApiRequestException::factory()->create(['created_at' => now()->subDays(20)]);

        Livewire::test(Show::class, ['requestId' => $exception->request_id])
            ->assertSet('expired', true)
            ->assertViewHas('tabs', fn (array $tabs): bool => array_keys($tabs) === ['overview', 'exceptions']);
    }

    public function test_the_timeline_lists_audit_entries_written_by_the_request(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $log = ApiRequestLog::factory()->create(['timings' => ['received' => 0, 'route_matched' => 4.2, 'response_ready' => 18.5]]);
        activity('audit')->event('updated')->withProperties(['module' => 'user', 'request_id' => $log->request_id])->log('updated');
        activity('audit')->event('updated')->withProperties(['module' => 'user', 'request_id' => 'req_SOMETHINGELSE00000000000000'])->log('updated');

        $data = Livewire::test(Show::class, ['requestId' => $log->request_id])
            ->call('selectTab', 'timeline')
            ->instance()
            ->activeTabData();

        $this->assertCount(1, $data['activities']);
        $this->assertSame(['received', 'route_matched', 'response_ready'], array_column($data['phases'], 'key'));
        $this->assertSame(1, Activity::where('properties->request_id', $log->request_id)->count());
    }

    public function test_related_requests_share_the_correlation_id(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'api_logs.requests.view']);
        $log = ApiRequestLog::factory()->create(['correlation_id' => 'flow-12345678']);
        $sibling = ApiRequestLog::factory()->create(['correlation_id' => 'flow-12345678', 'created_at' => now()->subMinute()]);
        ApiRequestLog::factory()->create();

        $related = Livewire::test(Show::class, ['requestId' => $log->request_id])->instance()->activeTabData()['related'];

        $this->assertSame([$sibling->request_id], $related->pluck('request_id')->all());
    }
}
