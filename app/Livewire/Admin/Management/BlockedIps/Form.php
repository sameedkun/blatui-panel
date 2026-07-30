<?php

namespace App\Livewire\Admin\Management\BlockedIps;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

/**
 * Create/edit page for a blocked IP — routed like every other Form in this
 * app (Plans, Categories, …), not a drawer. `scope` drives whether the block
 * is global (user_id null) or per-user (resolved from `formUserEmail`).
 * Delete stays on {@see Index}, mirroring `HandlesPlanRowActions`'s split
 * between a page-level Form and row-level delete.
 */
#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $blockedIpId = null;

    public string $ipAddress = '';

    public string $scope = 'user';

    public string $formUserEmail = '';

    public string $userSearch = '';

    public string $reason = '';

    public bool $permanent = false;

    public ?string $expiresAt = null;

    public bool $globalConfirmed = false;

    public function selectUser(string $email): void
    {
        $this->formUserEmail = $email;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->formUserEmail = '';
        $this->userSearch = '';
    }

    #[Computed]
    public function userSearchResults()
    {
        $term = trim($this->userSearch);

        return User::appUsers()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->take(10)
            ->get();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if (! $this->formUserEmail) {
            return null;
        }

        return User::appUsers()->where('email', $this->formUserEmail)->first();
    }

    protected function indexRoute(): string
    {
        return 'admin.blocked-ips.index';
    }

    /**
     * Route middleware already gates access to this page (`permission:blocked-ips.create`
     * / `permission:blocked-ips.update` on the create/edit routes — see routes/admin.php),
     * matching how every other Form in this app (Plans, Categories, …) relies solely on
     * route middleware rather than an inline authorize() in mount(). The one exception is
     * `blocked-ips.create-global`, which depends on a choice made inside the form itself
     * and so can't be expressed as static route middleware — that's checked in save().
     */
    public function mount(?BlockedIp $blockedIp = null): void
    {
        if ($blockedIp) {
            $blockedIp->loadMissing('user:id,email');

            $this->isEditing = true;
            $this->blockedIpId = $blockedIp->id;
            $this->ipAddress = $blockedIp->ip_address;
            $this->scope = $blockedIp->user_id ? 'user' : 'global';
            $this->formUserEmail = $blockedIp->user?->email ?? '';
            $this->reason = (string) $blockedIp->reason;
            $this->permanent = $blockedIp->expires_at === null;
            $this->expiresAt = $blockedIp->expires_at?->format('Y-m-d\TH:i');
            $this->globalConfirmed = true; // editing an already-existing global block doesn't need re-confirming
        } else {
            $this->scope = $this->canCreateGlobal() ? $this->scope : 'user';
            $this->expiresAt = now()->addDays(7)->format('Y-m-d\TH:i');
        }
    }

    public function canCreateGlobal(): bool
    {
        return auth()->user()->can('blocked-ips.create-global');
    }

    protected function rules(): array
    {
        return [
            'ipAddress' => ['required', 'ip'],
            'scope' => ['required', 'in:user,global'],
            'formUserEmail' => ['required_if:scope,user', 'nullable', 'email'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'permanent' => ['boolean'],
            'expiresAt' => ['required_if:permanent,false', 'nullable', 'date', 'after:now'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'ipAddress' => __('blocked_ips.validation_attributes.ip_address'),
            'scope' => __('blocked_ips.validation_attributes.scope'),
            'formUserEmail' => __('blocked_ips.validation_attributes.user_email'),
            'reason' => __('blocked_ips.validation_attributes.reason'),
            'permanent' => __('blocked_ips.validation_attributes.permanent'),
            'expiresAt' => __('blocked_ips.validation_attributes.expires_at'),
        ];
    }

    protected function messages(): array
    {
        return [
            'ipAddress.required' => __('blocked_ips.validation.ip_required'),
            'ipAddress.ip' => __('blocked_ips.validation.ip_invalid'),
            'scope.required' => __('blocked_ips.validation.scope_required'),
            'scope.in' => __('blocked_ips.validation.scope_invalid'),
            'formUserEmail.required_if' => __('blocked_ips.validation.user_email_required'),
            'formUserEmail.email' => __('blocked_ips.validation.user_email_invalid'),
            'reason.string' => __('blocked_ips.validation.reason_invalid'),
            'reason.max' => __('blocked_ips.validation.reason_max', ['max' => 1000]),
            'permanent.boolean' => __('blocked_ips.validation.permanent_invalid'),
            'expiresAt.required_if' => __('blocked_ips.validation.expires_required'),
            'expiresAt.date' => __('blocked_ips.validation.expires_invalid'),
            'expiresAt.after' => __('blocked_ips.validation.expires_future'),
        ];
    }

    public function updatedScope(): void
    {
        $this->globalConfirmed = false;
    }

    /** Distinct users seen on this IP over the last 30 days — powers the global-scope warning. */
    public function globalDistinctUserCount(): int
    {
        if ($this->ipAddress === '') {
            return 0;
        }

        return UserDevice::where('ip_address', $this->ipAddress)
            ->where('last_seen_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');
    }

    public function looksLikeCarrierNat(): bool
    {
        return $this->globalDistinctUserCount() >= (int) config('panel.blocked_ip_carrier_nat_threshold', 10);
    }

    public function save()
    {
        if (! $this->isEditing) {
            $this->authorizeCreateScope();
        }

        $this->validate();

        if ($this->scope === 'global' && ! $this->globalConfirmed) {
            $this->addError('scope', __('blocked_ips.validation.confirm_global'));

            return;
        }

        $userId = null;

        if ($this->scope === 'user') {
            $user = User::where('email', $this->formUserEmail)->first();

            if (! $user) {
                $this->addError('formUserEmail', __('blocked_ips.validation.user_not_found'));

                return;
            }

            $userId = $user->id;
        }

        if ($this->duplicateExists($userId)) {
            $this->addError('ipAddress', $this->scope === 'global'
                ? __('blocked_ips.validation.duplicate_global')
                : __('blocked_ips.validation.duplicate_user'));

            return;
        }

        $attributes = [
            'ip_address' => $this->ipAddress,
            'user_id' => $userId,
            'reason' => trim($this->reason) ?: null,
            'expires_at' => $this->permanent ? null : $this->expiresAt,
        ];

        try {
            if ($this->isEditing) {
                $blockedIp = BlockedIp::findOrFail($this->blockedIpId);
                $blockedIp->update($attributes);
                $action = ActivityAction::Updated;
            } else {
                $attributes['blocked_by'] = auth()->id();
                $blockedIp = BlockedIp::create($attributes);
                $action = ActivityAction::Created;
            }
        } catch (QueryException) {
            $this->addError('ipAddress', $this->scope === 'global'
                ? __('blocked_ips.validation.duplicate_global')
                : __('blocked_ips.validation.duplicate_user'));

            return;
        }

        if ($userId === null) {
            Log::warning('Global IP block created', [
                'ip_address' => $this->ipAddress,
                'distinct_users_last_30_days' => $this->globalDistinctUserCount(),
                'blocked_by' => auth()->id(),
            ]);
        }

        $this->logActivity(ActivityModule::BlockedIp, $action, $blockedIp, [
            'ip_address' => $blockedIp->ip_address,
            'scope' => $userId === null ? 'global' : 'user',
            'reason' => $blockedIp->reason,
            'expires_at' => $blockedIp->expires_at?->toIso8601String(),
        ]);

        return $this->redirectWithSuccess(
            $this->isEditing ? __('blocked_ips.toasts.updated') : __('blocked_ips.toasts.created'),
        );
    }

    protected function duplicateExists(?int $userId): bool
    {
        return BlockedIp::query()
            ->where('ip_address', $this->ipAddress)
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
            ->when($this->blockedIpId, fn ($q) => $q->whereKeyNot($this->blockedIpId))
            ->exists();
    }

    /**
     * The create route's `permission:blocked-ips.create` middleware already covers a
     * per-user block; a global one additionally needs `blocked-ips.create-global`, which
     * only becomes knowable once the form itself picks a scope.
     */
    protected function authorizeCreateScope(): void
    {
        if ($this->scope === 'global') {
            $this->authorize('blocked-ips.create-global');
        }
    }

    public function render(): View
    {
        return view('livewire.admin.management.blocked-ips.form')
            ->title($this->isEditing ? __('blocked_ips.form.edit_title') : __('blocked_ips.form.create_title'));
    }
}
