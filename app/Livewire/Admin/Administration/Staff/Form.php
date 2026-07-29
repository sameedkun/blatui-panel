<?php

namespace App\Livewire\Admin\Administration\Staff;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as RulesPassword;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $userId = null;

    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $password = '';

    /** @var array<int, string> */
    #[Validate]
    public array $roles = [];

    /** Staff emails are auto-verified by default; check this to leave it unverified instead. */
    public bool $sendVerificationEmail = false;

    public bool $forcePasswordReset = false;

    /** Whether the email was changed on edit — triggers the verify prompt. */
    public bool $emailChanged = false;

    /** Edit-only: skip the verification email and mark the new address verified immediately. */
    public bool $autoVerifyChangedEmail = false;

    protected function indexRoute(): string
    {
        return 'admin.staff.index';
    }

    public function mount(?User $user = null): void
    {
        if ($user) {
            abort_if(
                $user->isSuperAdmin() && ! auth()->user()->isSuperAdmin(),
                403,
                __('staff.errors.super_admin_protected'),
            );

            $this->isEditing = true;
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->roles = $user->roles()->pluck('name')->all();
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],

            'password' => [
                $this->isEditing ? 'nullable' : 'required',
                RulesPassword::defaults(),
            ],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')->where('guard_name', config('panel.guard'))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('staff.validation_attributes.name'),
            'email' => __('staff.validation_attributes.email'),
            'password' => __('staff.validation_attributes.password'),
            'roles' => __('staff.validation_attributes.roles'),
            'roles.*' => __('staff.validation_attributes.role'),
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('staff.validation.name_required'),
            'name.string' => __('staff.validation.name_invalid'),
            'name.max' => __('staff.validation.name_max', ['max' => 60]),
            'email.required' => __('staff.validation.email_required'),
            'email.email' => __('staff.validation.email_invalid'),
            'email.max' => __('staff.validation.email_max', ['max' => 255]),
            'email.unique' => __('staff.validation.email_unique'),
            'password.required' => __('staff.validation.password_required'),
            'password.min' => __('staff.validation.password_min', ['min' => 8]),
            'password.letters' => __('staff.validation.password_letters'),
            'password.mixed' => __('staff.validation.password_mixed'),
            'password.numbers' => __('staff.validation.password_numbers'),
            'password.symbols' => __('staff.validation.password_symbols'),
            'password.uncompromised' => __('staff.validation.password_uncompromised'),
            'roles.required' => __('staff.validation.roles_required'),
            'roles.array' => __('staff.validation.roles_invalid'),
            'roles.min' => __('staff.validation.roles_min'),
            'roles.*.exists' => __('staff.validation.role_exists'),
        ];
    }

    /** Roles selectable in the UI — the super-admin role is only offered to super-admins. */
    protected function assignableRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', config('panel.guard'))
            ->when(
                ! auth()->user()->isSuperAdmin(),
                fn ($q) => $q->where('name', '!=', config('panel.super_admin_role')),
            )
            ->orderBy('name')
            ->get();
    }

    /** [role name => "Title Case" label] for the roles select. */
    protected function roleOptions(): array
    {
        return $this->assignableRoles()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $this->roleLabel($role->name)])
            ->all();
    }

    /**
     * Permission names granted by the currently selected roles, deduplicated
     * across roles and grouped by module (the part before the first dot).
     *
     * @return Collection<string, Collection<int, string>>
     */
    protected function groupedSelectedPermissions(): Collection
    {
        if (empty($this->roles)) {
            return collect();
        }

        return Role::query()
            ->where('guard_name', config('panel.guard'))
            ->whereIn('name', $this->roles)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->groupBy(fn (string $name): string => Str::before($name, '.'));
    }

    public function updatedEmail(string $value): void
    {
        if ($this->isEditing) {
            $user = User::find($this->userId);
            $this->emailChanged = $user && $user->email !== $value;

            if (! $this->emailChanged) {
                $this->autoVerifyChangedEmail = false;
            }
        }
    }

    public function save(): mixed
    {
        $this->validate();

        // Only a super-admin may grant (or keep) the super-admin role.
        if (! auth()->user()->isSuperAdmin()) {
            $this->roles = array_values(array_diff($this->roles, [config('panel.super_admin_role')]));
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->isEditing) {
            $user = User::findOrFail($this->userId);

            abort_if(
                $user->isSuperAdmin() && ! auth()->user()->isSuperAdmin(),
                403,
                __('staff.errors.super_admin_protected'),
            );

            if (filled($this->password)) {
                $data['password'] = Hash::make($this->password);
                $data['password_changed_at'] = now();
            }

            if ($this->emailChanged) {
                $data['email_verified_at'] = $this->autoVerifyChangedEmail ? now() : null;
            }

            if ($this->forcePasswordReset) {
                $data['password_changed_at'] = null;
            }

            $before = $user->getOriginal();
            $rolesBefore = $user->roles->pluck('name')->sort()->values()->all();

            $user->update($data);
            $user->syncRoles($this->roles);

            $changes = $this->auditDiff($user, $before);
            $rolesAfter = collect($this->roles)->sort()->values()->all();
            if ($rolesBefore != $rolesAfter) {
                $changes['roles'] = ['attributes' => $rolesAfter, 'old' => $rolesBefore];
            }

            if ($changes !== [] || filled($this->password)) {
                $this->logActivity(ActivityModule::Staff, ActivityAction::Updated, $user,
                    $changes + (filled($this->password) ? ['password_changed' => true] : []));
            }

            if ($this->emailChanged && ! $this->autoVerifyChangedEmail) {
                $user->sendEmailVerificationNotification();
            }

            if ($this->forcePasswordReset) {
                Password::sendResetLink(['email' => $user->email]);
            }

            return $this->redirectWithSuccess(__('staff.toasts.updated', ['name' => $user->name]));
        }

        $user = User::create([
            ...$data,
            'password' => Hash::make($this->password),
            'email_verified_at' => $this->sendVerificationEmail ? null : now(),
            'registration_date' => now(),
            'type' => UserType::Staff,
        ]);

        $user->syncRoles($this->roles);

        $this->logActivity(ActivityModule::Staff, ActivityAction::Created, $user, [
            'attributes' => [
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'roles' => $this->roles,
            ],
        ]);

        if ($this->sendVerificationEmail) {
            $user->sendEmailVerificationNotification();
        }

        return $this->redirectWithSuccess(__('staff.toasts.created', ['name' => $user->name]));
    }

    public function render(): View
    {
        $groupedPermissions = $this->groupedSelectedPermissions();

        return view('livewire.admin.administration.staff.form', [
            'roleOptions' => $this->roleOptions(),
            'groupedPermissions' => $groupedPermissions,
            'moduleLabels' => $groupedPermissions->keys()->mapWithKeys(
                fn (string $module): array => [$module => $this->moduleLabel($module)],
            ),
            'permissionLabels' => $groupedPermissions
                ->flatten()
                ->mapWithKeys(fn (string $permission): array => [$permission => $this->permissionLabel($permission)]),
        ])->title($this->isEditing ? __('staff.form.edit_title') : __('staff.form.create_title'));
    }

    private function roleLabel(string $name): string
    {
        $key = 'staff.role_labels.'.str_replace('-', '_', $name);
        $translation = __($key);

        return $translation === $key ? Str::headline($name) : $translation;
    }

    private function moduleLabel(string $module): string
    {
        $staffKey = 'staff.permissions.modules.'.str_replace('-', '_', $module);
        $staffTranslation = __($staffKey);
        if ($staffTranslation !== $staffKey) {
            return $staffTranslation;
        }

        $key = 'navigation.modules.'.str_replace('-', '_', $module);
        $translation = __($key);

        return $translation === $key
            ? config("panel.modules.{$module}.label", Str::headline($module))
            : $translation;
    }

    private function permissionLabel(string $permission): string
    {
        $segments = explode('.', $permission);
        array_shift($segments);
        $action = array_pop($segments) ?? '';
        $actionKey = 'staff.permissions.actions.'.$action;
        $actionLabel = __($actionKey);

        if ($actionLabel === $actionKey) {
            $actionLabel = Str::headline($action);
        }

        if ($segments === []) {
            return $actionLabel;
        }

        $scope = implode('_', $segments);
        $scopeKey = "staff.permissions.scopes.{$scope}";
        $scopeLabel = __($scopeKey);

        return __('staff.permissions.scoped', [
            'scope' => $scopeLabel === $scopeKey ? Str::headline(implode(' ', $segments)) : $scopeLabel,
            'action' => $actionLabel,
        ]);
    }
}
