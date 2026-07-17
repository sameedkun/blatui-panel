{{-- Confirmation dialogs for the Staff index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row ─────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="ban-user"
    title="Ban Staff Member"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="banReason"
    confirm="confirmBan"
    confirm-label="Ban Staff Member"
    cancel="$wire.set('banningUserId', null)"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="delete-user"
    title="Delete Staff Member"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will soft-delete the staff account. They can be restored later.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    title="Restore Staff Member"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    confirm-label="Restore"
>
    This will restore the staff account.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    title="Permanently Delete"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. The staff account and all associated data will be permanently removed.
</x-admin.confirm-dialog>

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    title="Ban {{ count($selectedIds) }} Staff"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="bulkBanReason"
    confirm="executeBulkBan"
    confirm-label="Ban Staff"
    cancel="$wire.cancelBulkAction()"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    title="Unban {{ count($selectedIds) }} Staff"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Unban"
>
    This will lift the ban on all selected staff. Protected super admin accounts are skipped automatically.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Staff"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    Staff will be soft-deleted and can be restored later. Protected super admin accounts are skipped automatically.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    title="Restore {{ count($selectedIds) }} Staff"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Restore"
>
    All selected deleted staff will be restored.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    title="Permanently Delete {{ count($selectedIds) }} Staff"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. All selected staff will be permanently removed.
</x-admin.confirm-dialog>
