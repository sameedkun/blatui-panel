{{-- Confirmation dialogs for the Staff index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row ─────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="ban-user"
    :title="__('staff.dialogs.ban_title')"
    :description="__('staff.dialogs.ban_description', ['reason' => __('staff.defaults.ban_reason')])"
    model="banReason"
    confirm="confirmBan"
    :confirm-label="__('staff.dialogs.ban_title')"
    cancel="$wire.set('banningUserId', null)"
    :label="__('staff.fields.reason')"
    :placeholder="__('staff.dialogs.ban_placeholder')"
/>

<x-admin.confirm-dialog
    id="delete-user"
    :title="__('staff.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('staff.actions.delete')"
    variant="destructive"
>
    {{ __('staff.dialogs.delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    :title="__('staff.dialogs.restore_title')"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    :confirm-label="__('staff.actions.restore')"
>
    {{ __('staff.dialogs.restore_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    :title="__('staff.dialogs.force_delete_title')"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    :confirm-label="__('staff.actions.delete_permanently')"
    variant="destructive"
>
    {{ __('staff.dialogs.force_delete_description') }}
</x-admin.confirm-dialog>

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    :title="__('staff.dialogs.bulk_ban_title', ['count' => count($selectedIds)])"
    :description="__('staff.dialogs.ban_description', ['reason' => __('staff.defaults.ban_reason')])"
    model="bulkBanReason"
    confirm="executeBulkBan"
    :confirm-label="__('staff.actions.ban')"
    cancel="$wire.cancelBulkAction()"
    :label="__('staff.fields.reason')"
    :placeholder="__('staff.dialogs.ban_placeholder')"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    :title="__('staff.dialogs.bulk_unban_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('staff.actions.unban')"
>
    {{ __('staff.dialogs.bulk_unban_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('staff.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('staff.actions.delete')"
    variant="destructive"
>
    {{ __('staff.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    :title="__('staff.dialogs.bulk_restore_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('staff.actions.restore')"
>
    {{ __('staff.dialogs.bulk_restore_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    :title="__('staff.dialogs.bulk_force_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('staff.actions.delete_permanently')"
    variant="destructive"
>
    {{ __('staff.dialogs.bulk_force_delete_description') }}
</x-admin.confirm-dialog>
