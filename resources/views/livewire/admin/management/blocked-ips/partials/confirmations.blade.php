{{-- Confirmation drawers for the Blocked IPs index. Included by index.blade — shares its Livewire scope. --}}

<x-admin.confirm-dialog
    id="delete-blocked-ip"
    :title="__('blocked_ips.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('blocked_ips.actions.delete')"
    variant="destructive"
>
    {{ __('blocked_ips.dialogs.delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="delete-all-expired"
    :title="__('blocked_ips.dialogs.expired_title', ['count' => $this->expiredCount()])"
    confirm="$wire.deleteAllExpired()"
    cancel="$wire.set('confirmingDeleteAllExpired', false)"
    :confirm-label="__('blocked_ips.dialogs.delete_all')"
    variant="destructive"
>
    {{ __('blocked_ips.dialogs.expired_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('blocked_ips.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('blocked_ips.actions.delete')"
    variant="destructive"
>
    {{ __('blocked_ips.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>
