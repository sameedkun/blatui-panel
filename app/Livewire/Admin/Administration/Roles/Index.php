<?php

namespace App\Livewire\Admin\Administration\Roles;

use App\Livewire\Admin\BaseIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
#[Title('Roles')]
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
                'label' => 'Total Roles',
                'value' => fn () => Role::where('guard_name', $guard)->count(),
                'icon' => 'key',
                'description' => 'All roles in the panel',
            ],
            [
                'label' => 'Custom Roles',
                'value' => fn () => Role::where('guard_name', $guard)->whereNotIn('name', $protected)->count(),
                'icon' => 'user-cog',
                'description' => 'Created from this panel',
            ],
            [
                'label' => 'Protected Roles',
                'value' => fn () => Role::where('guard_name', $guard)->whereIn('name', $protected)->count(),
                'icon' => 'lock',
                'description' => 'Locked system roles',
            ],
            [
                'label' => 'Total Permissions',
                'value' => fn () => Permission::where('guard_name', $guard)->count(),
                'icon' => 'shield-check',
                'description' => 'Available across all modules',
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
        abort_if($this->isProtected($role), 403, 'Protected roles cannot be deleted.');

        $this->deletingId = $roleId;
        $this->deletingStaffCount = $role->users()->count();
        $this->dispatch('open-alert-dialog-delete-role');
    }

    public function delete(): void
    {
        $this->authorize('roles.delete');

        $role = Role::findOrFail($this->deletingId);
        abort_if($this->isProtected($role), 403, 'Protected roles cannot be deleted.');

        $name = $role->name;
        $role->delete();

        $this->deletingId = null;
        $this->toastSuccess("{$name} role deleted.");
    }

    public function render(): View
    {
        $roles = $this->getRecords();

        return view('livewire.admin.administration.roles.index', [
            'roles' => $roles,
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
