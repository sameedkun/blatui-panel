{{-- Confirmation dialogs for the Ticket Categories index. Included by
     index.blade — shares its Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-category"
    :title="__('ticket_categories.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('ticket_categories.actions.delete')"
    variant="destructive"
>
    {{ __('ticket_categories.dialogs.delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-activate"
    :title="__('ticket_categories.dialogs.activate_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkActivate()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('ticket_categories.actions.activate')"
>
    {{ __('ticket_categories.dialogs.activate_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-deactivate"
    :title="__('ticket_categories.dialogs.deactivate_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDeactivate()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('ticket_categories.actions.deactivate')"
>
    {{ __('ticket_categories.dialogs.deactivate_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('ticket_categories.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('ticket_categories.actions.delete')"
    variant="destructive"
>
    {{ __('ticket_categories.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>
