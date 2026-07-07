<?php

namespace App\Livewire\Admin\System\Staff;

use App\Enum\UserType;
use App\Livewire\Admin\BaseForm;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
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
                'Super admin accounts are protected.',
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
                Password::defaults(),
            ],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')->where('guard_name', config('panel.guard'))],
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
            ->mapWithKeys(fn (Role $role): array => [$role->name => Str::headline($role->name)])
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
        }
    }

    public function save()
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
                'Super admin accounts are protected.',
            );

            if (filled($this->password)) {
                $data['password'] = Hash::make($this->password);
                $data['password_changed_at'] = now();
            }

            if ($this->emailChanged) {
                $data['email_verified_at'] = null;
            }

            if ($this->forcePasswordReset) {
                $data['password_changed_at'] = null;
            }

            $user->update($data);
            $user->syncRoles($this->roles);

            return $this->redirectWithSuccess("{$user->name} updated successfully.");
        }

        $user = User::create([
            ...$data,
            'password' => Hash::make($this->password),
            'email_verified_at' => $this->sendVerificationEmail ? null : now(),
            'registration_date' => now(),
            'type' => UserType::Staff,
        ]);

        $user->syncRoles($this->roles);

        return $this->redirectWithSuccess("{$user->name} created successfully.");
    }

    public function render(): View
    {
        return view('livewire.admin.system.staff.form', [
            'roleOptions' => $this->roleOptions(),
            'groupedPermissions' => $this->groupedSelectedPermissions(),
        ]);
    }
}
