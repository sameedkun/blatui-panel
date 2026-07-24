{{-- Confirmation dialogs for the Languages index. Included by index.blade —
     shares its Livewire scope ($wire, $deletingId, $selectedIds, etc.). --}}

<x-admin.confirm-dialog
    id="delete-language"
    title="Delete Language"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will permanently delete the language. This action cannot be undone.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Languages"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    This permanently deletes all selected languages. This action <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>
