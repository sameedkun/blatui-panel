{{-- Confirmation dialogs for the Ticket Categories index. Included by
     index.blade — shares its Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-category"
    title="Delete Category"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will permanently delete the category. This action cannot be undone.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-activate"
    title="Activate {{ count($selectedIds) }} Categories"
    confirm="$wire.executeBulkActivate()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Activate"
>
    Every selected category becomes available for ticket routing.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-deactivate"
    title="Deactivate {{ count($selectedIds) }} Categories"
    confirm="$wire.executeBulkDeactivate()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Deactivate"
>
    Every selected category stops being available for ticket routing.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Categories"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    This permanently deletes every selected category that has no tickets. This action <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>
