<?php

namespace Tests\Feature;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\PolicyType;
use App\Livewire\Admin\Settings\Policies as PoliciesSettings;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ActivityPresenter;
use Database\Seeders\PoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Settings > Policies page logs every mutation under the single "setting"
 * module, so ActivityPresenter is what has to tell a privacy-policy change
 * apart from a terms-of-service change when scanning the audit trail.
 */
class ActivityPresenterPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** @return array{label: string, value: string|array<int, string>}|null */
    private function findRow(array $rows, string $label): ?array
    {
        return collect($rows)->firstWhere('label', $label);
    }

    public function test_privacy_update_presents_a_distinct_title_and_shows_the_version(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAsSuperAdmin();

        Livewire::test(PoliciesSettings::class)
            ->set('policies.privacy.version', '1.1')
            ->set('policies.privacy.content', 'Brand new privacy content.')
            ->call('save');

        $activity = Activity::where('event', 'updated')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Privacy Policy Updated', $presented['title']);
        $this->assertSame('1.1', $this->findRow($presented['rows'], 'Version')['value'] ?? null);
    }

    public function test_terms_update_presents_a_distinct_title_and_shows_the_version(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAsSuperAdmin();

        Livewire::test(PoliciesSettings::class)
            ->set('policies.terms.version', '2.0')
            ->set('policies.terms.content', 'Brand new terms content.')
            ->call('save');

        $activity = Activity::where('event', 'updated')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Terms of Service Updated', $presented['title']);
        $this->assertSame('2.0', $this->findRow($presented['rows'], 'Version')['value'] ?? null);
    }

    /** Saving the form untouched shouldn't fabricate audit entries for sections that didn't change. */
    public function test_saving_without_changes_logs_nothing(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAsSuperAdmin();

        Livewire::test(PoliciesSettings::class)->call('save');

        $this->assertSame(0, Activity::query()->count());
    }

    /** Changing only the privacy policy must not also log a terms update. */
    public function test_updating_only_privacy_does_not_log_a_terms_activity(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAsSuperAdmin();

        Livewire::test(PoliciesSettings::class)
            ->set('policies.privacy.version', '1.1')
            ->set('policies.privacy.content', 'Brand new privacy content.')
            ->call('save');

        $this->assertSame(1, Activity::query()->count());
    }

    /**
     * The presenter must stay useful for a policy area that doesn't map to a
     * known {@see PolicyType} case (e.g. a retired type, or one
     * logged by code from before a case existed) — proof that a future policy
     * type (a refund policy, say) needs no ActivityPresenter changes to show
     * up sensibly, only a new enum case.
     */
    public function test_an_unmapped_policy_area_still_presents_a_sensible_title(): void
    {
        $this->actingAsSuperAdmin();

        // "cookies" deliberately isn't (and may never become) a PolicyType
        // case — this is the fallback path for a log row whose area doesn't
        // map to a known enum value.
        ActivityLogger::log(ActivityModule::Setting, ActivityAction::Updated, null, [
            'area' => 'policy_cookies',
            'version' => '1.0',
        ]);

        $activity = Activity::where('event', 'updated')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Cookies Updated', $presented['title']);
        $this->assertSame('1.0', $this->findRow($presented['rows'], 'Version')['value'] ?? null);
    }
}
