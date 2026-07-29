<?php

namespace App\Livewire\Admin\Administration\Staff;

use App\Livewire\Admin\BaseIndex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    // ── Single-row confirmation state ─────────────────────────────────────────

    public ?int $banningUserId = null;

    public string $banReason = '';

    public ?int $deletingId = null;

    public ?int $restoringId = null;

    public ?int $forceDeleteId = null;

    // ── Filters ───────────────────────────────────────────────────────────────

    public array $filters = [
        'status' => [],
        'role' => [],
        'registered_from' => '',
        'registered_to' => '',
        'view' => '',
    ];

    // ── Base query ────────────────────────────────────────────────────────────
    protected function baseQuery(): Builder
    {
        $query = User::query()->staff()->with('roles');

        if (($this->filters['view'] ?? '') === 'trashed') {
            $query->onlyTrashed();
        }

        return $query;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'external_id'];
    }

    /** [role name => "Title Case" label] — kept keyed by the raw name for filtering. */
    protected function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', config('panel.guard'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $this->roleLabel($role->name)])
            ->all();
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('staff.fields.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('staff.status.active'), 'banned' => __('staff.status.banned')],
                'apply' => function (Builder $q, array $values): Builder {
                    return $q->where(function (Builder $sub) use ($values): void {
                        foreach ($values as $v) {
                            match ($v) {
                                'active' => $sub->orWhereNull('banned_at'),
                                'banned' => $sub->orWhereNotNull('banned_at'),
                                default => null,
                            };
                        }
                    });
                },
            ],
            'role' => [
                'label' => __('staff.fields.roles'),
                'type' => 'multi-select',
                'options' => $this->roleOptions(),
                'apply' => fn (Builder $q, array $values): Builder => $q->whereHas(
                    'roles',
                    fn (Builder $r): Builder => $r->whereIn('name', $values),
                ),
            ],
            'registered_from' => [
                'label' => __('staff.filters.registered_from'),
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '>=', $v),
            ],
            'registered_to' => [
                'label' => __('staff.filters.registered_to'),
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '<=', $v.' 23:59:59'),
            ],
            'view' => [
                'label' => __('staff.filters.view'),
                'type' => 'select',
                'options' => ['trashed' => __('staff.status.deleted')],
                'apply' => fn (Builder $q, string $v): Builder => $q, // handled in baseQuery()
            ],
        ];
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('staff.stats.total_staff'),
                'value' => fn () => User::staff()->count(),
                'icon' => 'shield-user',
                'description' => __('staff.stats.all_staff_accounts'),
            ],
            [
                'label' => __('staff.status.active'),
                'value' => fn () => User::staff()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => __('staff.stats.not_banned'),
            ],
            [
                'label' => __('staff.status.banned'),
                'value' => fn () => User::staff()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => __('staff.stats.banned_accounts'),
            ],
            [
                'label' => __('staff.stats.super_admins'),
                'value' => fn () => User::superAdmins()->count(),
                'icon' => 'crown',
                'description' => __('staff.stats.full_system_access'),
            ],
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => __('staff.fields.status'),
                'type' => 'multi-select',
                'options' => ['active' => __('staff.status.active'), 'banned' => __('staff.status.banned')],
            ],
            'role' => [
                'label' => __('staff.fields.roles'),
                'type' => 'multi-select',
                'options' => $this->roleOptions(),
            ],
            'registered' => [
                'label' => __('staff.fields.registered'),
                'type' => 'date-range',
                'from_key' => 'registered_from',
                'to_key' => 'registered_to',
            ],
            'view' => [
                'label' => __('staff.status.deleted'),
                'type' => 'toggle',
                'active_value' => 'trashed',
                'active_label' => __('staff.filters.showing_deleted'),
                'icon' => 'trash-2',
            ],
        ];
    }

    // ── Bulk action config ────────────────────────────────────────────────────

    protected function bulkActionConfig(): array
    {
        $isTrashed = ($this->filters['view'] ?? '') === 'trashed';

        if ($isTrashed) {
            return [
                [
                    'key' => 'restore',
                    'label' => __('staff.actions.restore'),
                    'icon' => 'rotate-ccw',
                    'confirm' => true,
                    'permission' => 'staff.restore',
                ],
                [
                    'key' => 'force-delete',
                    'label' => __('staff.actions.force_delete'),
                    'icon' => 'trash-2',
                    'confirm' => true,
                    'variant' => 'destructive',
                    'permission' => 'staff.force-delete',
                ],
            ];
        }

        return [
            [
                'key' => 'ban',
                'label' => __('staff.actions.ban'),
                'icon' => 'ban',
                'confirm' => true,
                'dialog_event' => 'open-dialog-bulk-ban',
                'permission' => 'staff.ban',
            ],
            [
                'key' => 'unban',
                'label' => __('staff.actions.unban'),
                'icon' => 'shield-check',
                'confirm' => true,
                'permission' => 'staff.unban',
            ],
            [
                'key' => 'delete',
                'label' => __('staff.actions.delete'),
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'staff.delete',
            ],
        ];
    }

    // ── Super-admin protection ────────────────────────────────────────────────
    // Non-super-admins may never mutate a super-admin's account; super-admins
    // may act on anyone, including other super-admins. Nobody — not even a
    // super-admin — may ban/delete/restore/force-delete their own account.

    protected function assertCanManage(User $target): void
    {
        abort_if(
            $target->id === auth()->id(),
            403,
            __('staff.errors.self_action'),
        );

        abort_if(
            $target->isSuperAdmin() && ! auth()->user()->isSuperAdmin(),
            403,
            __('staff.errors.super_admin_protected'),
        );
    }

    /** IDs from the current selection that the acting user is allowed to mutate. */
    protected function allowedSelectedIds(bool $withTrashed = false): array
    {
        $query = $withTrashed ? User::withTrashed() : User::query();
        $query->whereIn('id', $this->selectedIds)->where('id', '!=', auth()->id());

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', config('panel.super_admin_role')));
        }

        return $query->pluck('id')->all();
    }

    // ── Single-row actions ────────────────────────────────────────────────────

    public function openBanDialog(int $userId): void
    {
        $this->authorize('staff.ban');
        $this->assertCanManage(User::findOrFail($userId));

        $this->banningUserId = $userId;
        $this->banReason = '';
        $this->dispatch('open-dialog-ban-user');
    }

    public function confirmBan(): void
    {
        $this->authorize('staff.ban');

        $user = User::findOrFail($this->banningUserId);
        $this->assertCanManage($user);

        $user->update([
            'banned_at' => now(),
            'ban_reason' => trim($this->banReason) ?: __('staff.defaults.ban_reason'),
        ]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess(__('staff.toasts.banned', ['name' => $user->name]));
    }

    public function unban(int $userId): void
    {
        $this->authorize('staff.unban');

        $user = User::findOrFail($userId);
        $this->assertCanManage($user);

        $user->update(['banned_at' => null, 'ban_reason' => null]);

        $this->toastSuccess(__('staff.toasts.unbanned', ['name' => $user->name]));
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('staff.delete');
        $this->assertCanManage(User::findOrFail($userId));

        $this->deletingId = $userId;
        $this->dispatch('open-alert-dialog-delete-user');
    }

    public function delete(): void
    {
        $this->authorize('staff.delete');

        $user = User::findOrFail($this->deletingId);
        $this->assertCanManage($user);

        $name = $user->name;
        $user->delete();

        $this->deletingId = null;
        $this->toastSuccess(__('staff.toasts.deleted', ['name' => $name]));
    }

    public function confirmRestore(int $userId): void
    {
        $this->authorize('staff.restore');
        $this->assertCanManage(User::withTrashed()->findOrFail($userId));

        $this->restoringId = $userId;
        $this->dispatch('open-alert-dialog-restore-user');
    }

    public function restore(): void
    {
        $this->authorize('staff.restore');

        $user = User::withTrashed()->findOrFail($this->restoringId);
        $this->assertCanManage($user);

        $user->restore();

        $this->restoringId = null;
        $this->toastSuccess(__('staff.toasts.restored', ['name' => $user->name]));
    }

    public function confirmForceDelete(int $userId): void
    {
        $this->authorize('staff.force-delete');
        $this->assertCanManage(User::withTrashed()->findOrFail($userId));

        $this->forceDeleteId = $userId;
        $this->dispatch('open-alert-dialog-force-delete-user');
    }

    public function forceDelete(): void
    {
        $this->authorize('staff.force-delete');

        $user = User::withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertCanManage($user);

        $name = $user->name;
        $user->forceDelete();

        $this->forceDeleteId = null;
        $this->toastSuccess(__('staff.toasts.force_deleted', ['name' => $name]));
    }

    // ── Bulk actions (protected super-admin rows are silently skipped) ───────

    public function executeBulkBan(): void
    {
        $this->authorize('staff.ban');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->update([
            'banned_at' => now(),
            'ban_reason' => trim($this->bulkBanReason) ?: __('staff.defaults.ban_reason'),
        ]);

        $this->clearSelection();
        $this->toastSuccess(trans_choice('staff.toasts.bulk_banned', count($ids), ['count' => count($ids)]));
    }

    public function executeBulkUnban(): void
    {
        $this->authorize('staff.unban');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->update(['banned_at' => null, 'ban_reason' => null]);

        $this->clearSelection();
        $this->toastSuccess(trans_choice('staff.toasts.bulk_unbanned', count($ids), ['count' => count($ids)]));
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('staff.delete');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->delete();

        $this->clearSelection();
        $this->toastSuccess(trans_choice('staff.toasts.bulk_deleted', count($ids), ['count' => count($ids)]));
    }

    public function executeBulkRestore(): void
    {
        $this->authorize('staff.restore');

        $ids = $this->allowedSelectedIds(withTrashed: true);
        User::withTrashed()->whereIn('id', $ids)->restore();

        $this->clearSelection();
        $this->toastSuccess(trans_choice('staff.toasts.bulk_restored', count($ids), ['count' => count($ids)]));
    }

    public function executeBulkForceDelete(): void
    {
        $this->authorize('staff.force-delete');

        $ids = $this->allowedSelectedIds(withTrashed: true);
        User::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->clearSelection();
        $this->toastSuccess(trans_choice('staff.toasts.bulk_force_deleted', count($ids), ['count' => count($ids)]));
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $staff = $this->getRecords();

        return view('livewire.admin.administration.staff.index', [
            'staff' => $staff,
            'pageIds' => $staff->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'roleLabels' => $this->roleOptions(),
        ])->title(__('staff.title'));
    }

    private function roleLabel(string $name): string
    {
        $key = 'staff.role_labels.'.str_replace('-', '_', $name);
        $translation = __($key);

        return $translation === $key ? Str::headline($name) : $translation;
    }
}
