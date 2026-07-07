<?php

namespace App\Livewire\Admin\System\Roles;

use App\Livewire\Admin\BaseForm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    public ?int $roleId = null;

    #[Validate]
    public string $name = '';

    /** @var array<int, string> */
    public array $permissions = [];

    /** Protected roles (super-admin, admin) are re-synced from config on every seed — view only. */
    public bool $isProtectedRole = false;

    protected function indexRoute(): string
    {
        return 'admin.roles.index';
    }

    public function mount(?Role $role = null): void
    {
        if ($role) {
            $this->isEditing = true;
            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->permissions = $role->permissions()->pluck('name')->all();
            $this->isProtectedRole = in_array($role->name, config('panel.protected_roles', []), true);
        } else {
            // New roles grant panel access by default.
            $this->permissions = array_values(config('panel.access', []));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->where('guard_name', config('panel.guard'))->ignore($this->roleId),
            ],

            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where('guard_name', config('panel.guard'))],
        ];
    }

    public function save()
    {
        abort_if($this->isProtectedRole, 403, 'Protected roles cannot be edited.');

        $this->validate();

        if ($this->isEditing) {
            $role = Role::findOrFail($this->roleId);
            $role->update(['name' => $this->name]);
        } else {
            $role = Role::create(['name' => $this->name, 'guard_name' => config('panel.guard')]);
        }

        $role->syncPermissions($this->permissions);

        return $this->redirectWithSuccess(
            "{$role->name} role ".($this->isEditing ? 'updated' : 'created').' successfully.',
        );
    }

    /** [permission => label] for the panel-access checkboxes at the top of the form. */
    protected function panelAccessOptions(): array
    {
        return collect(config('panel.access', []))
            ->mapWithKeys(fn (string $permission, string $panel): array => [$permission => Str::headline($panel).' Panel'])
            ->all();
    }

    /**
     * Modules grouped by their config('panel.groups') category, in that
     * category's declared order. Each module carries its label and an
     * [permission-name => action-label] map for the checkboxes.
     *
     * @return Collection<string, Collection<int, array>>
     */
    protected function moduleGroups(): Collection
    {
        $groupLabels = config('panel.groups', []);
        $groupOrder = array_keys($groupLabels);

        return collect(config('panel.modules', []))
            ->map(function (array $module, string $key): array {
                $actions = $module['actions'] ?? [];

                return [
                    'key' => $key,
                    'label' => $module['label'] ?? Str::headline($key),
                    'group' => $module['group'] ?? 'system',
                    'permissions' => collect($actions)->mapWithKeys(
                        fn (string $action): array => ["{$key}.{$action}" => Str::headline($action)],
                    ),
                ];
            })
            ->groupBy('group')
            ->sortBy(function (Collection $modules, string $group) use ($groupOrder): int {
                $position = array_search($group, $groupOrder, true);

                return $position === false ? count($groupOrder) : $position;
            })
            ->mapWithKeys(fn (Collection $modules, string $group): array => [
                ($groupLabels[$group] ?? Str::headline($group)) => $modules->values(),
            ]);
    }

    public function render(): View
    {
        return view('livewire.admin.system.roles.form', [
            'panelAccessOptions' => $this->panelAccessOptions(),
            'moduleGroups' => $this->moduleGroups(),
            'allPermissionNames' => Permission::where('guard_name', config('panel.guard'))->pluck('name')->all(),
        ]);
    }
}
