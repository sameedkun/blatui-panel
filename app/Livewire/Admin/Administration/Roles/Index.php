<?php

namespace App\Livewire\Admin\Administration\Roles;

use App\Livewire\Admin\BaseIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    public string $sortBy = 'name';

    public string $sortDir = 'asc';

    public ?int $deletingId = null;

    public int $deletingStaffCount = 0;

    protected function baseQuery(): Builder
    {
        return Role::query()
            ->where('guard_name', config('panel.guard'))
            ->withCount(['permissions', 'users']);
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function statsConfig(): array
    {
        $guard = config('panel.guard');
        $protected = config('panel.protected_roles', []);

        return [
            [
                'label' => __('roles.stats.total_roles'),
                'value' => fn () => Role::where('guard_name', $guard)->count(),
                'icon' => 'key',
                'description' => __('roles.stats.all_panel_roles'),
            ],
            [
                'label' => __('roles.stats.custom_roles'),
                'value' => fn () => Role::where('guard_name', $guard)->whereNotIn('name', $protected)->count(),
                'icon' => 'user-cog',
                'description' => __('roles.stats.created_from_panel'),
            ],
            [
                'label' => __('roles.stats.protected_roles'),
                'value' => fn () => Role::where('guard_name', $guard)->whereIn('name', $protected)->count(),
                'icon' => 'lock',
                'description' => __('roles.stats.locked_system_roles'),
            ],
            [
                'label' => __('roles.stats.total_permissions'),
                'value' => fn () => Permission::where('guard_name', $guard)->count(),
                'icon' => 'shield-check',
                'description' => __('roles.stats.available_all_modules'),
            ],
        ];
    }

    protected function isProtected(Role $role): bool
    {
        return in_array($role->name, config('panel.protected_roles', []), true);
    }

    public function confirmDelete(int $roleId): void
    {
        $this->authorize('roles.delete');

        $role = Role::findOrFail($roleId);
        abort_if($this->isProtected($role), 403, __('roles.errors.protected_delete'));

        $this->deletingId = $roleId;
        $this->deletingStaffCount = $role->users()->count();
        $this->dispatch('open-alert-dialog-delete-role');
    }

    public function delete(): void
    {
        $this->authorize('roles.delete');

        $role = Role::findOrFail($this->deletingId);
        abort_if($this->isProtected($role), 403, __('roles.errors.protected_delete'));

        $name = $role->name;
        $role->delete();

        $this->deletingId = null;
        $this->toastSuccess(__('roles.toasts.deleted', ['name' => $this->roleLabel($name)]));
    }

    public function render(): View
    {
        $roles = $this->getRecords();

        return view('livewire.admin.administration.roles.index', [
            'roles' => $roles,
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'roleLabels' => $roles->getCollection()
                ->mapWithKeys(fn (Role $role): array => [$role->name => $this->roleLabel($role->name)])
                ->all(),
        ])->title(__('roles.title'));
    }

    private function roleLabel(string $name): string
    {
        $key = 'roles.role_labels.'.str_replace('-', '_', $name);
        $translation = __($key);

        return $translation === $key ? Str::headline($name) : $translation;
    }
}
