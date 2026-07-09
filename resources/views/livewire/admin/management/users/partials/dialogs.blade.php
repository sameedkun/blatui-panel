{{-- Confirmation dialogs for the Users index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the user profile page) ─────────────────────── --}}

@include('livewire.admin.management.users.partials.single-row-dialogs')

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

{{-- ── Account deletion (grace period) ────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="schedule-deletion"
    title="Schedule Account Deletion"
    description="The account stays live during the grace period, then is permanently purged. You can stop it before then."
    model="deletionReason"
    confirm="confirmScheduleDeletion"
    confirm-label="Schedule Deletion"
    variant="destructive"
    cancel="$wire.set('schedulingId', null)"
    placeholder="Reason for deletion (optional)"
/>

<x-admin.reason-dialog
    id="instant-purge"
    title="Purge Account Now"
    description="This permanently deletes the account immediately, skipping the grace period."
    model="purgeReason"
    confirm="instantPurge"
    confirm-label="Purge Permanently"
    variant="destructive"
    cancel="$wire.set('purgingId', null)"
    placeholder="Reason (optional)"
/>

<x-admin.reason-dialog
    id="bulk-schedule-deletion"
    title="Schedule {{ count($selectedIds) }} Accounts for Deletion"
    description="Each account stays live during the grace period, then is permanently purged."
    model="bulkDeletionReason"
    confirm="executeBulkScheduleDeletion"
    confirm-label="Schedule Deletion"
    variant="destructive"
    cancel="$wire.cancelBulkAction()"
    placeholder="Reason for deletion (optional)"
/>

<x-admin.confirm-dialog
    id="bulk-stop-deletion"
    title="Stop Deletion for {{ count($selectedIds) }} Accounts"
    confirm="$wire.executeBulkStopDeletion()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Stop Deletion"
>
    This cancels the pending deletion for all selected accounts.
</x-admin.confirm-dialog>

<x-admin.reason-dialog
    id="bulk-instant-purge"
    title="Purge {{ count($selectedIds) }} Accounts Now"
    description="This permanently deletes all selected accounts immediately, skipping the grace period."
    model="bulkPurgeReason"
    confirm="executeBulkInstantPurge"
    confirm-label="Purge Permanently"
    variant="destructive"
    cancel="$wire.cancelBulkAction()"
    placeholder="Reason (optional)"
/>
