{{-- Confirmation dialogs for the Guests index. Included by index.blade — shares
     its Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the guest profile page) ─────────────────────── --}}

@include('livewire.admin.management.guests.partials.single-row-dialogs')

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    :title="__('guests.dialogs.bulk_ban_title', ['count' => count($selectedIds)])"
    :description="__('guests.dialogs.ban_guest_desc')"
    model="bulkBanReason"
    confirm="executeBulkBan"
    :confirm-label="__('guests.actions.ban')"
    cancel="$wire.cancelBulkAction()"
    :placeholder="__('guests.dialogs.ban_reason_placeholder')"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    :title="__('guests.dialogs.bulk_unban_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('guests.actions.unban')"
>
    {{ __('guests.dialogs.bulk_unban_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('guests.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('common.force_delete')"
    variant="destructive"
>
    {{ __('guests.dialogs.bulk_delete_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    :title="__('guests.dialogs.bulk_restore_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('common.restore')"
>
    {{ __('guests.dialogs.bulk_restore_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    :title="__('guests.dialogs.bulk_force_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('common.force_delete')"
    variant="destructive"
>
    {{ __('guests.dialogs.bulk_force_delete_desc') }}
</x-admin.confirm-dialog>
