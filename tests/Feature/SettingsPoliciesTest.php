<?php

namespace Tests\Feature;

use App\Enum\PolicyType;
use App\Livewire\Admin\Settings\Policies as PoliciesComponent;
use App\Models\Policy;
use App\Models\User;
use Database\Seeders\PoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    private function staffWith(array $abilities): User
    {
        foreach (['panel.access-admin', 'settings.view', 'settings.policies.view', 'settings.policies.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_page_403s_without_settings_view(): void
    {
        $this->actingAs($this->staffWith([])); // panel access only

        $this->get(route('admin.settings.policies'))->assertForbidden();
    }

    public function test_parent_settings_view_grants_policies_view_access(): void
    {
        $this->actingAs($this->staffWith(['settings.view']));

        $this->get(route('admin.settings.policies'))->assertOk();
    }

    public function test_page_loads_and_displays_seeded_policies(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view']));

        $this->get(route('admin.settings.policies'))->assertOk();

        Livewire::test(PoliciesComponent::class)
            ->assertSet('policies.privacy.title', 'Privacy Policy')
            ->assertSet('policies.privacy.version', '1.0')
            ->assertSet('policies.privacy.content', 'This is the default Privacy Policy content. Please edit it in the admin panel.')
            ->assertSet('policies.terms.title', 'Terms of Service')
            ->assertSet('policies.terms.version', '1.0')
            ->assertSet('policies.terms.content', 'This is the default Terms of Service content. Please edit it in the admin panel.');
    }

    public function test_editing_policies_is_forbidden_without_edit_permission(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view'])); // no edit permission

        Livewire::test(PoliciesComponent::class)
            ->set('policies.privacy.content', 'Sneaky change')
            ->call('save')
            ->assertForbidden();
    }

    public function test_editing_policies_creates_new_version_and_deactivates_old(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view', 'settings.policies.edit']));

        $privacyPolicy = Policy::where('key', 'privacy')->first();
        $oldVersion = $privacyPolicy->activeVersion()->first();

        Livewire::test(PoliciesComponent::class)
            ->set('policies.privacy.title', 'Updated Privacy Policy')
            ->set('policies.privacy.version', '1.1')
            ->set('policies.privacy.content', 'Brand new privacy policy content.')
            ->call('save')
            ->assertHasNoErrors();

        // Check policy title updated
        $this->assertEquals('Updated Privacy Policy', $privacyPolicy->fresh()->title);

        // Check new version created and marked active
        $newActiveVersion = $privacyPolicy->activeVersion()->first();
        $this->assertNotNull($newActiveVersion);
        $this->assertEquals('1.1', $newActiveVersion->version);
        $this->assertEquals('Brand new privacy policy content.', $newActiveVersion->content);
        $this->assertTrue($newActiveVersion->is_active);

        // Check old version marked inactive
        $this->assertFalse($oldVersion->fresh()->is_active);
        $this->assertEquals(2, $privacyPolicy->versions()->count());
    }

    /**
     * A {@see PolicyType} case with no content written yet (e.g. one
     * just added to the enum) must not block saving the rest of the form, and
     * must not get a garbage empty version published behind the scenes.
     */
    public function test_saving_does_not_publish_an_empty_version_for_an_unauthored_policy_type(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view', 'settings.policies.edit']));

        // Mounting firstOrCreate()s a Policy row per PolicyType case; any case the
        // seeder didn't publish content for is what this test is guarding.
        Livewire::test(PoliciesComponent::class);
        $unauthoredKeys = Policy::query()->whereDoesntHave('versions')->pluck('key')->all();
        $this->assertNotEmpty($unauthoredKeys, 'Expected at least one PolicyType case the seeder leaves unpublished.');

        Livewire::test(PoliciesComponent::class)
            ->set('policies.privacy.version', '1.1')
            ->set('policies.privacy.content', 'Brand new privacy policy content.')
            ->call('save')
            ->assertHasNoErrors();

        foreach ($unauthoredKeys as $key) {
            $this->assertSame(0, Policy::where('key', $key)->first()->versions()->count());
        }
    }

    public function test_version_history_lists_every_saved_version_newest_first(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view', 'settings.policies.edit']));

        Livewire::test(PoliciesComponent::class)
            ->set('policies.privacy.version', '1.1')
            ->set('policies.privacy.content', 'Second privacy version.')
            ->call('save')
            ->assertHasNoErrors();

        $privacy = Policy::where('key', 'privacy')->first();
        $this->assertSame(2, $privacy->versions()->count());

        $ordered = $privacy->versions()->orderByDesc('published_at')->orderByDesc('id')->pluck('version')->all();
        $this->assertSame(['1.1', '1.0'], $ordered);
    }

    public function test_viewing_a_version_loads_its_full_content_into_the_dialog(): void
    {
        $this->seed(PoliciesSeeder::class);
        $this->actingAs($this->staffWith(['settings.view', 'settings.policies.view', 'settings.policies.edit']));

        $version = Policy::where('key', 'privacy')->first()->activeVersion()->first();

        Livewire::test(PoliciesComponent::class)
            ->call('viewVersion', $version->id)
            ->assertSet('viewingVersionId', $version->id)
            ->assertDispatched('open-dialog-policy-version')
            ->assertSee($version->content);
    }

    public function test_user_model_acceptance_helpers(): void
    {
        $this->seed(PoliciesSeeder::class);
        $user = User::factory()->create();

        $privacy = Policy::where('key', 'privacy')->first();
        $activeVersion = $privacy->activeVersion()->first();

        $this->assertFalse($user->hasAcceptedPolicy('privacy'));

        $user->acceptPolicy($privacy);

        $this->assertTrue($user->hasAcceptedPolicy('privacy'));
        $this->assertTrue($user->hasAcceptedPolicy($privacy));

        // Check acceptance recorded correctly
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $user->id,
            'policy_version_id' => $activeVersion->id,
        ]);
    }
}
