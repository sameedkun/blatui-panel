{{-- Confirmation dialogs for the Guests index. Included by index.blade — shares
     its Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the guest profile page) ─────────────────────── --}}

@include('livewire.admin.management.guests.partials.single-row-dialogs')

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    title="Ban {{ count($selectedIds) }} Guests"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="bulkBanReason"
    confirm="executeBulkBan"
    confirm-label="Ban Guests"
    cancel="$wire.cancelBulkAction()"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    title="Unban {{ count($selectedIds) }} Guests"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Unban"
>
    This will lift the ban on all selected guests.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Guests"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This permanently deletes all selected guests and their associated data. This <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    title="Restore {{ count($selectedIds) }} Guests"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Restore"
>
    All selected deleted guests will be restored.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    title="Permanently Delete {{ count($selectedIds) }} Guests"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. All selected guests will be permanently removed.
</x-admin.confirm-dialog>
