<?php

namespace App\Livewire\Admin\Account;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The authenticated staff member's own account page — self-service, never an
 * admin acting on someone else. It always operates on {@see auth()->user()} and
 * is deliberately NOT route-model bound, so there is no way to point it at
 * another account.
 *
 * A single scrolling page (settings pages are conventionally scroll, not tabs)
 * with three sections: profile, password/security, and the user's own audit
 * trail. Every privileged mutation here — email change, password change, the
 * session purge — is audit-logged as a staff credential event.
 */
#[Layout('layouts.admin.app')]
class Index extends Component
{
    use HasToast;
    use LogsAdminActivity;
    use WithFileUploads;

    // ── Profile ────────────────────────────────────────────────────────────────

    public string $name = '';

    public string $email = '';

    /** A newly picked avatar image, pending save. */
    public ?TemporaryUploadedFile $avatarUpload = null;

    // ── Password / security ─────────────────────────────────────────────────────

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Confirms identity for the "log out other devices" action (its own field so it can't be confused with a password change). */
    public string $logout_password = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    /** The account being managed is always the logged-in user — never bound input. */
    protected function user(): User
    {
        return auth()->user();
    }

    /**
     * Email is the account-recovery vector, so only a super-admin may change
     * their own. Checked against the configured role, never a hardcoded string.
     */
    public function canEditEmail(): bool
    {
        return $this->user()->hasRole(config('panel.super_admin_role'));
    }

    // ── Profile save ────────────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        $user = $this->user();

        // Defense-in-depth: a readonly input is not security. Reject an email
        // change from anyone who isn't a super-admin, whatever the client sent.
        if ($this->email !== $user->email && ! $this->canEditEmail()) {
            abort(403, __('account.errors.email_change_forbidden'));
        }

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatarUpload' => ['nullable', 'image', 'max:2048'],
        ]);

        $emailChanged = $this->email !== $user->email;

        $data = ['name' => $this->name, 'email' => $this->email];

        if ($this->avatarUpload) {
            $path = $this->avatarUpload->store('avatars'); // default disk, auto unique name

            if ($user->avatar) {
                Storage::delete($user->avatar); // default disk too
            }

            $data['avatar'] = $path; // store the PATH, not a URL
        }

        // A new email must be re-verified — it hasn't been proven to belong to the user.
        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }

        $before = $user->getOriginal();
        $user->update($data);

        // Audit the change (the email old→new diff is the privileged credential event).
        $changes = $this->auditDiff($user, $before, extraExcept: ['avatar']);
        if ($changes !== [] || $this->avatarUpload) {
            $this->logActivity(
                ActivityModule::Staff,
                ActivityAction::Updated,
                $user,
                $changes + ($this->avatarUpload ? ['avatar_changed' => true] : [])
            );
        }

        $this->avatarUpload = null;
        $this->toastSuccess(__('account.toasts.profile_updated'));
    }

    // ── Password change ─────────────────────────────────────────────────────────

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->user();
        $user->forceFill([
            'password' => Hash::make($this->password),
            'password_changed_at' => now(),
        ])->save();

        // Keep THIS session alive: changing the password rotates the hash that
        // AuthenticateSession compares, and the Livewire request never passes
        // through that middleware to re-stamp it.
        $this->refreshCurrentSessionPasswordHash();

        $this->logActivity(ActivityModule::Staff, ActivityAction::Updated, $user, ['password_changed' => true]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toastSuccess(__('account.toasts.password_updated'));
    }

    // ── Log out other devices ────────────────────────────────────────────────────

    public function logoutOtherDevices(): void
    {
        $this->validate([
            'logout_password' => ['required', 'current_password'],
        ]);

        $user = $this->user();

        // Rotates the session password hash — every other session fails the
        // AuthenticateSession check on its next request. Works with Redis
        // sessions because nothing is enumerated; the hash is the kill switch.
        Auth::logoutOtherDevices($this->logout_password);

        // Invalidate every "remember me" cookie too, so a remembered device
        // can't silently re-authenticate after this action.
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->refreshCurrentSessionPasswordHash();

        $this->logActivity(ActivityModule::Staff, ActivityAction::Updated, $user, ['logged_out_other_sessions' => true]);

        $this->reset('logout_password');
        $this->toastSuccess(__('account.toasts.other_sessions_logged_out'));
    }

    /** Re-stamp the current session with the live password hash so this device stays signed in. */
    private function refreshCurrentSessionPasswordHash(): void
    {
        $guard = config('auth.defaults.guard');

        session()->put('password_hash_'.$guard, $this->user()->getAuthPassword());
    }

    // ── Read-only account facts ───────────────────────────────────────────────────

    /**
     * The permissions granted by the current user's role(s), grouped by module,
     * for the optional "your access" disclosure.
     *
     * @return Collection<string, Collection<int, string>>
     */
    protected function groupedPermissions(): Collection
    {
        return $this->user()
            ->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->groupBy(fn (string $name): string => Str::before($name, '.'));
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('account.validation_attributes.name'),
            'email' => __('account.validation_attributes.email'),
            'avatarUpload' => __('account.validation_attributes.avatar'),
            'current_password' => __('account.validation_attributes.current_password'),
            'password' => __('account.validation_attributes.new_password'),
            'logout_password' => __('account.validation_attributes.password'),
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('account.validation.name_required'),
            'name.string' => __('account.validation.name_invalid'),
            'name.max' => __('account.validation.name_max', ['max' => 60]),
            'email.required' => __('account.validation.email_required'),
            'email.email' => __('account.validation.email_invalid'),
            'email.max' => __('account.validation.email_max', ['max' => 255]),
            'email.unique' => __('account.validation.email_unique'),
            'avatarUpload.image' => __('account.validation.avatar_image'),
            'avatarUpload.max' => __('account.validation.avatar_max', ['max' => 2]),
            'current_password.required' => __('account.validation.current_password_required'),
            'current_password.current_password' => __('account.validation.current_password_incorrect'),
            'password.required' => __('account.validation.password_required'),
            'password.confirmed' => __('account.validation.password_confirmation'),
            'password.min' => __('account.validation.password_min', ['min' => app()->isProduction() ? 12 : 8]),
            'password.letters' => __('account.validation.password_letters'),
            'password.mixed' => __('account.validation.password_mixed'),
            'password.numbers' => __('account.validation.password_numbers'),
            'password.symbols' => __('account.validation.password_symbols'),
            'password.uncompromised' => __('account.validation.password_uncompromised'),
            'logout_password.required' => __('account.validation.logout_password_required'),
            'logout_password.current_password' => __('account.validation.logout_password_incorrect'),
        ];
    }

    public function render(): View
    {
        $user = $this->user();
        $roles = $user->getRoleNames();
        $groupedPermissions = $this->groupedPermissions();

        return view('livewire.admin.account.index', [
            'user' => $user,
            'roles' => $roles,
            'roleLabels' => $roles->mapWithKeys(
                fn (string $role): array => [$role => $this->roleLabel($role)],
            ),
            'groupedPermissions' => $groupedPermissions,
            'moduleLabels' => $groupedPermissions->keys()->mapWithKeys(
                fn (string $module): array => [$module => $this->moduleLabel($module)],
            ),
            'permissionLabels' => $groupedPermissions
                ->flatten()
                ->mapWithKeys(fn (string $permission): array => [$permission => $this->permissionLabel($permission)]),
            // The user's own audit trail — sourced from the causer relation, never rebuilt.
            'recentActivity' => $user->activitiesAsCauser()->latest()->limit(8)->get(),
            'canViewFullLog' => $user->can('activity_logs.view'),
        ])->title(__('account.title'));
    }

    private function roleLabel(string $name): string
    {
        $key = 'roles.role_labels.'.str_replace('-', '_', $name);
        $translation = __($key);

        return $translation === $key ? Str::headline($name) : $translation;
    }

    private function moduleLabel(string $module): string
    {
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
        $actionKey = 'roles.permissions.actions.'.$action;
        $actionLabel = __($actionKey);

        if ($actionLabel === $actionKey) {
            $actionLabel = Str::headline($action);
        }

        if ($segments === []) {
            return $actionLabel;
        }

        $scopeKey = 'roles.permissions.scopes.'.implode('_', $segments);
        $scopeLabel = __($scopeKey);

        return __('roles.permissions.scoped', [
            'scope' => $scopeLabel === $scopeKey ? Str::headline(implode(' ', $segments)) : $scopeLabel,
            'action' => $actionLabel,
        ]);
    }
}
