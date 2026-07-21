<?php

namespace App\Livewire\Admin\Administration\Roles;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
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
    use LogsAdminActivity;

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
            $before = $role->getOriginal();
            $permsBefore = $role->permissions->pluck('name')->sort()->values()->all();

            $role->update(['name' => $this->name]);
            $role->syncPermissions($this->permissions);

            $changes = $this->auditDiff($role, $before);
            $permsAfter = collect($this->permissions)->sort()->values()->all();
            if ($permsBefore != $permsAfter) {
                $changes['permissions'] = ['attributes' => $permsAfter, 'old' => $permsBefore];
            }

            if ($changes !== []) {
                $this->logActivity(ActivityModule::Role, ActivityAction::Updated, $role, $changes);
            }
        } else {
            $role = Role::create(['name' => $this->name, 'guard_name' => config('panel.guard')]);
            $role->syncPermissions($this->permissions);

            $this->logActivity(ActivityModule::Role, ActivityAction::Created, $role, [
                'attributes' => ['name' => $role->name, 'permissions' => $this->permissions],
            ]);
        }

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
     * Recursively flatten actions into permission name => label map.
     */
    protected function flattenActions(string $prefix, array $actions): array
    {
        $result = [];

        foreach ($actions as $key => $value) {
            if (is_array($value)) {
                $subPrefix = "{$prefix}.{$key}";
                foreach ($value as $subAction) {
                    $permission = "{$subPrefix}.{$subAction}";
                    $result[$permission] = $this->getPermissionLabel($permission);
                }
            } else {
                $permission = "{$prefix}.{$value}";
                $result[$permission] = $this->getPermissionLabel($permission);
            }
        }

        return $result;
    }

    /**
     * Generate user-friendly descriptive labels for permissions.
     */
    protected function getPermissionLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        if (count($parts) >= 3) {
            array_shift($parts); // Remove the module prefix (e.g. 'settings')

            return collect($parts)->map(fn ($p) => Str::headline($p))->implode(' ');
        }

        return Str::headline(end($parts));
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

                $permissions = $this->flattenActions($key, $actions);

                if (isset($module['children']) && is_array($module['children'])) {
                    foreach ($module['children'] as $child => $childActions) {
                        $childPermissions = $this->flattenActions("{$key}.{$child}", $childActions);
                        $permissions = array_merge($permissions, $childPermissions);
                    }
                }

                return [
                    'key' => $key,
                    'label' => $module['label'] ?? Str::headline($key),
                    'group' => $module['group'] ?? 'system',
                    'permissions' => collect($permissions),
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
        return view('livewire.admin.administration.roles.form', [
            'panelAccessOptions' => $this->panelAccessOptions(),
            'moduleGroups' => $this->moduleGroups(),
            'allPermissionNames' => Permission::where('guard_name', config('panel.guard'))->pluck('name')->all(),
        ]);
    }
}
