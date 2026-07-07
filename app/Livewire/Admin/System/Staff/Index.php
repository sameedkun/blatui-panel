<?php

namespace App\Livewire\Admin\System\Staff;

use App\Livewire\Admin\BaseIndex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin.app')]
#[Title('Staff')]
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
            ->mapWithKeys(fn (Role $role): array => [$role->name => Str::headline($role->name)])
            ->all();
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'multi-select',
                'options' => ['active' => 'Active', 'banned' => 'Banned'],
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
                'label' => 'Role',
                'type' => 'multi-select',
                'options' => $this->roleOptions(),
                'apply' => fn (Builder $q, array $values): Builder => $q->whereHas(
                    'roles',
                    fn (Builder $r): Builder => $r->whereIn('name', $values),
                ),
            ],
            'registered_from' => [
                'label' => 'Registered from',
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '>=', $v),
            ],
            'registered_to' => [
                'label' => 'Registered to',
                'type' => 'date',
                'apply' => fn (Builder $q, string $v): Builder => $q->where('registration_date', '<=', $v.' 23:59:59'),
            ],
            'view' => [
                'label' => 'View',
                'type' => 'select',
                'options' => ['trashed' => 'Deleted'],
                'apply' => fn (Builder $q, string $v): Builder => $q, // handled in baseQuery()
            ],
        ];
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Staff',
                'value' => fn () => User::staff()->count(),
                'icon' => 'shield-user',
                'description' => 'All staff accounts',
            ],
            [
                'label' => 'Active',
                'value' => fn () => User::staff()->whereNull('banned_at')->count(),
                'icon' => 'user-check',
                'description' => 'Not banned',
            ],
            [
                'label' => 'Banned',
                'value' => fn () => User::staff()->whereNotNull('banned_at')->count(),
                'icon' => 'user-x',
                'description' => 'Banned accounts',
            ],
            [
                'label' => 'Super Admins',
                'value' => fn () => User::superAdmins()->count(),
                'icon' => 'crown',
                'description' => 'Full system access',
            ],
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'multi-select',
                'options' => ['active' => 'Active', 'banned' => 'Banned'],
            ],
            'role' => [
                'label' => 'Role',
                'type' => 'multi-select',
                'options' => $this->roleOptions(),
            ],
            'registered' => [
                'label' => 'Registered',
                'type' => 'date-range',
                'from_key' => 'registered_from',
                'to_key' => 'registered_to',
            ],
            'view' => [
                'label' => 'Deleted',
                'type' => 'toggle',
                'active_value' => 'trashed',
                'active_label' => 'Showing Deleted',
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
                    'label' => 'Restore',
                    'icon' => 'rotate-ccw',
                    'confirm' => true,
                    'permission' => 'staff.restore',
                ],
                [
                    'key' => 'force-delete',
                    'label' => 'Permanently Delete',
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
                'label' => 'Ban',
                'icon' => 'ban',
                'confirm' => true,
                'dialog_event' => 'open-dialog-bulk-ban',
                'permission' => 'staff.ban',
            ],
            [
                'key' => 'unban',
                'label' => 'Unban',
                'icon' => 'shield-check',
                'confirm' => true,
                'permission' => 'staff.unban',
            ],
            [
                'key' => 'delete',
                'label' => 'Delete',
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
            'You cannot perform this action on your own account.',
        );

        abort_if(
            $target->isSuperAdmin() && ! auth()->user()->isSuperAdmin(),
            403,
            'Super admin accounts are protected.',
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
            'ban_reason' => trim($this->banReason) ?: 'Banned by administrator.',
        ]);

        $this->banningUserId = null;
        $this->banReason = '';
        $this->toastSuccess("{$user->name} has been banned.");
    }

    public function unban(int $userId): void
    {
        $this->authorize('staff.unban');

        $user = User::findOrFail($userId);
        $this->assertCanManage($user);

        $user->update(['banned_at' => null, 'ban_reason' => null]);

        $this->toastSuccess("{$user->name} has been unbanned.");
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
        $this->toastSuccess("{$name} has been deleted.");
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
        $this->toastSuccess("{$user->name} has been restored.");
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
        $this->toastSuccess("{$name} has been permanently deleted.");
    }

    // ── Bulk actions (protected super-admin rows are silently skipped) ───────

    public function executeBulkBan(): void
    {
        $this->authorize('staff.ban');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->update([
            'banned_at' => now(),
            'ban_reason' => trim($this->bulkBanReason) ?: 'Banned by administrator.',
        ]);

        $this->clearSelection();
        $this->toastSuccess(count($ids).' staff banned.');
    }

    public function executeBulkUnban(): void
    {
        $this->authorize('staff.unban');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->update(['banned_at' => null, 'ban_reason' => null]);

        $this->clearSelection();
        $this->toastSuccess(count($ids).' staff unbanned.');
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('staff.delete');

        $ids = $this->allowedSelectedIds();
        User::whereIn('id', $ids)->delete();

        $this->clearSelection();
        $this->toastSuccess(count($ids).' staff deleted.');
    }

    public function executeBulkRestore(): void
    {
        $this->authorize('staff.restore');

        $ids = $this->allowedSelectedIds(withTrashed: true);
        User::withTrashed()->whereIn('id', $ids)->restore();

        $this->clearSelection();
        $this->toastSuccess(count($ids).' staff restored.');
    }

    public function executeBulkForceDelete(): void
    {
        $this->authorize('staff.force-delete');

        $ids = $this->allowedSelectedIds(withTrashed: true);
        User::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->clearSelection();
        $this->toastSuccess(count($ids).' staff permanently deleted.');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $staff = $this->getRecords();

        return view('livewire.admin.system.staff.index', [
            'staff' => $staff,
            'pageIds' => $staff->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
