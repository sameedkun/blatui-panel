{{-- Confirmation drawers for the Blocked IPs index. Included by index.blade — shares its Livewire scope. --}}

<x-admin.confirm-drawer
    id="delete-blocked-ip"
    title="Delete Block"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This immediately stops enforcing the block. Traffic from this IP will be allowed through again. This action <strong>cannot be undone</strong>.
</x-admin.confirm-drawer>

<x-admin.confirm-drawer
    id="delete-all-expired"
    title="Delete {{ $this->expiredCount() }} Expired Blocks"
    confirm="$wire.deleteAllExpired()"
    cancel="$wire.set('confirmingDeleteAllExpired', false)"
    confirm-label="Delete All"
    variant="destructive"
>
    Every block whose expiry has already passed will be permanently removed. This action <strong>cannot be undone</strong>.
</x-admin.confirm-drawer>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Blocks"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    Every selected block will be permanently removed. This action <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>
