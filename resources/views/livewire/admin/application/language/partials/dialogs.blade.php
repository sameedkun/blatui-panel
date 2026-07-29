{{-- Confirmation dialogs for the Languages index. Included by index.blade —
     shares its Livewire scope ($wire, $deletingId, $selectedIds, etc.). --}}

<x-admin.confirm-dialog
    id="delete-language"
    :title="__('languages.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('languages.actions.delete')"
    variant="destructive"
>
    {{ __('languages.dialogs.delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('languages.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('languages.actions.delete')"
    variant="destructive"
>
    {{ __('languages.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>
