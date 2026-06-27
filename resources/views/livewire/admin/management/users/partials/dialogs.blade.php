{{-- Confirmation dialogs for the Users index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row ─────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="ban-user"
    title="Ban User"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="banReason"
    confirm="confirmBan"
    confirm-label="Ban User"
    cancel="$wire.set('banningUserId', null)"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="delete-user"
    title="Delete User"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will soft-delete the user. They can be restored later.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    title="Restore User"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    confirm-label="Restore"
>
    This will restore the user's account.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    title="Permanently Delete"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. The user and all associated data will be permanently removed.
</x-admin.confirm-dialog>

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    title="Ban {{ count($selectedIds) }} Users"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="bulkBanReason"
    confirm="executeBulkBan"
    confirm-label="Ban Users"
    cancel="$wire.cancelBulkAction()"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    title="Unban {{ count($selectedIds) }} Users"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Unban"
>
    This will lift the ban on all selected users.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Users"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    Users will be soft-deleted and can be restored later.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    title="Restore {{ count($selectedIds) }} Users"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Restore"
>
    All selected deleted users will be restored.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    title="Permanently Delete {{ count($selectedIds) }} Users"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. All selected users will be permanently removed.
</x-admin.confirm-dialog>
