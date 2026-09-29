<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Enum\ActivityAction;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Dashboard\Analytics;
use App\Livewire\Admin\Dashboard\Index;
use App\Models\BlockedIp;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ActivityLogger;
use App\Support\Dashboard\Analytics\AnalyticsSection;
use App\Support\Dashboard\Blocks\Block;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DashboardCache;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_overview_renders_every_slot_for_a_super_admin_with_data(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedPlatformData();

        $overview = Livewire::test(Index::class)->assertOk()->viewData('overview');

        foreach (DashboardRegistry::OVERVIEW_SLOTS as $slot) {
            $this->assertNotEmpty($overview[$slot], "The [{$slot}] slot should render for a super admin.");
        }

        $this->assertCount(5, $overview['kpis']);
        $this->assertContainsOnlyInstancesOf(Metric::class, $overview['kpis']);
    }

    public function test_overview_widgets_are_filtered_by_their_own_permissions(): void
    {
        $this->actingAsStaffWith(['dashboard.view', 'users.view']);

        $overview = Livewire::test(Index::class)->assertOk()->viewData('overview');
        $kpiLabels = array_map(fn (Metric $metric): string => $metric->label, $overview['kpis']);

        $this->assertSame([__('dashboard.kpis.total_users')], $kpiLabels);
        $this->assertCount(1, $overview['trends'], 'Only the user-growth trend is visible without subscriptions.view.');
        $this->assertSame([], $overview['status']);
        $this->assertSame([], $overview['activity']);
    }

    public function test_quick_actions_carry_their_permissions_and_skip_unknown_routes(): void
    {
        $this->actingAsSuperAdmin();
        config()->set('dashboard.overview_actions', [
            ['label' => 'dashboard.actions.create_user', 'route' => 'admin.users.create', 'permission' => 'users.create'],
            ['label' => 'Missing', 'route' => 'admin.does-not-exist'],
        ]);

        $actions = Livewire::test(Index::class)->viewData('overview')['actions'][0];

        $this->assertCount(1, $actions->actions);
        $this->assertSame('users.create', $actions->actions[0]['permission']);
    }

    public function test_an_unknown_range_falls_back_to_the_default_and_presets_can_be_selected(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::withQueryParams(['range' => 'forever'])
            ->test(Index::class)
            ->assertSet('range', DateRange::DEFAULT)
            ->call('selectRange', '90d')
            ->assertSet('range', '90d')
            ->call('selectRange', 'bogus')
            ->assertSet('range', '90d');
    }

    public function test_refresh_invalidates_cached_payloads(): void
    {
        $this->actingAsSuperAdmin();
        config()->set('dashboard.cache_seconds', 300);

        $cache = app(DashboardCache::class);
        $range = DateRange::preset('30d');
        $calls = 0;
        $compute = function () use (&$calls): int {
            return ++$calls;
        };

        $cache->remember('probe', $range, $compute);
        $cache->remember('probe', $range, $compute);
        $this->assertSame(1, $calls, 'A second read is served from cache.');

        Livewire::test(Index::class)->call('refresh');

        $cache->remember('probe', $range, $compute);
        $this->assertSame(2, $calls, 'Refresh forces a recompute.');
    }

    public function test_cached_blocks_survive_a_store_that_refuses_to_unserialize_objects(): void
    {
        // Mirror production: a serializing store, with object unserialization disabled.
        config()->set('cache.stores.array.serialize', true);
        config()->set('cache.serializable_classes', false);
        config()->set('dashboard.cache_seconds', 300);
        Cache::forgetDriver('array');

        $this->actingAsSuperAdmin();
        $this->seedPlatformData();

        Livewire::test(Index::class)->assertOk();
        $cached = Livewire::test(Index::class)->assertOk()->viewData('overview');
        $this->assertContainsOnlyInstancesOf(Metric::class, $cached['kpis']);

        Livewire::withQueryParams(['tab' => 'audience'])->test(Analytics::class)->assertOk();
        $rows = Livewire::withQueryParams(['tab' => 'audience'])->test(Analytics::class)->assertOk()->viewData('rows');
        $this->assertContainsOnlyInstancesOf(Row::class, $rows);
    }

    public function test_the_cache_never_instantiates_classes_other_than_dashboard_blocks(): void
    {
        config()->set('cache.stores.array.serialize', true);
        config()->set('dashboard.cache_seconds', 300);
        Cache::forgetDriver('array');

        $cache = app(DashboardCache::class);
        $range = DateRange::preset('30d');

        $cache->remember('probe', $range, fn (): array => [Metric::make('Users', 1), new \ArrayObject([1])]);
        $restored = $cache->remember('probe', $range, fn (): array => []);

        $this->assertInstanceOf(Metric::class, $restored[0]);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $restored[1]);
    }

    public function test_analytics_requires_its_permission(): void
    {
        $this->actingAsStaffWith(['users.view']);

        $this->get(route('admin.dashboard.analytics'))->assertForbidden();
    }

    public function test_dashboard_view_grants_the_analytics_and_reports_pages(): void
    {
        $this->actingAsStaffWith(['dashboard.view', 'users.view']);

        $this->get(route('admin.dashboard.analytics'))->assertOk();
        $this->get(route('admin.dashboard.reports'))->assertOk();
    }

    public function test_analytics_tabs_are_filtered_and_a_hidden_tab_cannot_be_selected(): void
    {
        $this->actingAsStaffWith(['dashboard.analytics.view', 'users.view', 'tickets.view']);

        $component = Livewire::withQueryParams(['tab' => 'revenue'])->test(Analytics::class);

        $this->assertSame(['audience', 'support'], array_keys($component->viewData('sections')));
        $this->assertSame('audience', $component->viewData('active')->key(), 'A tab the viewer cannot open falls back to the first one.');

        $component->call('selectTab', 'revenue')
            ->assertSet('tab', 'revenue')
            ->call('selectTab', 'support')
            ->assertSet('tab', 'support');

        $this->assertSame('support', $component->viewData('active')->key());
    }

    #[DataProvider('sectionsAndRanges')]
    public function test_every_analytics_section_builds_for_every_range(string $section, string $range): void
    {
        $this->actingAsSuperAdmin();
        $this->seedPlatformData();

        $rows = Livewire::withQueryParams(['tab' => $section, 'range' => $range])
            ->test(Analytics::class)
            ->assertOk()
            ->viewData('rows');

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(Row::class, $rows);

        foreach ($rows as $row) {
            $this->assertContainsOnlyInstancesOf(Block::class, $row->blocks);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sectionsAndRanges(): array
    {
        $cases = [];

        foreach (['audience', 'revenue', 'subscriptions', 'support', 'security', 'api'] as $section) {
            foreach (DateRange::PRESETS as $range) {
                $cases["{$section} {$range}"] = [$section, $range];
            }
        }

        return $cases;
    }

    public function test_an_application_can_register_its_own_analytics_section(): void
    {
        $this->actingAsSuperAdmin();
        config()->set('dashboard.analytics', [...config('dashboard.analytics'), CustomSection::class]);

        $component = Livewire::withQueryParams(['tab' => 'servers'])->test(Analytics::class)->assertOk();

        $this->assertSame('servers', $component->viewData('active')->key());
        $this->assertSame('Nodes online', $component->viewData('rows')[0]->blocks[0]->label);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** @param  list<string>  $permissions */
    private function actingAsStaffWith(array $permissions): User
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);

        foreach (['panel.access-admin', ...$permissions] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $staff->assignRole($role);
        $this->actingAs($staff);

        return $staff;
    }

    /** A little of everything, spread across the last year, so every block has something to show. */
    private function seedPlatformData(): void
    {
        $users = User::factory()->count(6)->create(['type' => 'app', 'created_at' => now()->subDays(20)]);
        User::factory()->guest()->count(2)->create();
        $agent = User::factory()->create(['type' => 'staff']);

        foreach ($users as $user) {
            ActivityLogger::log(ActivityModule::User, ActivityAction::Login, $user, causer: $user, logName: ActivityLogName::Authentication);
        }
        ActivityLogger::log(ActivityModule::User, ActivityAction::Failed, properties: ['email' => 'nobody@example.com'], causer: null, logName: ActivityLogName::Authentication);

        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        $subscription = Subscription::factory()->create(['user_id' => $users[0]->id, 'plan_id' => $plan->id, 'plan_price_id' => $price->id, 'starts_at' => now()->subDays(3)]);
        Subscription::factory()->trialing()->create(['user_id' => $users[1]->id, 'plan_id' => $plan->id, 'plan_price_id' => $price->id]);
        Subscription::factory()->cancelled()->create(['user_id' => $users[2]->id, 'plan_id' => $plan->id, 'plan_price_id' => $price->id, 'starts_at' => now()->subMonths(2)]);
        SubscriptionReceipt::factory()->create(['subscription_id' => $subscription->id, 'type' => 'renewal']);

        $ticket = Ticket::factory()->create(['user_id' => $users[3]->id, 'assigned_to' => $agent->id, 'status' => 'open', 'created_at' => now()->subDays(2)]);
        TicketMessage::factory()->fromStaff($agent)->create(['ticket_id' => $ticket->id]);

        UserDevice::factory()->create(['user_id' => $users[4]->id]);
        BlockedIp::factory()->create();
    }
}

class CustomSection extends AnalyticsSection
{
    public function key(): string
    {
        return 'servers';
    }

    public function label(): string
    {
        return 'Servers';
    }

    public function description(): string
    {
        return 'VPN node health.';
    }

    public function icon(): string
    {
        return 'server';
    }

    public function build(DateRange $range): array
    {
        return [Row::columns(1, Metric::make('Nodes online', 42))];
    }
}
