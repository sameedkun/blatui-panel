{{-- Confirmation dialogs for the Users index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the user profile page) ─────────────────────── --}}

@include('livewire.admin.management.users.partials.single-row-dialogs')

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="bulk-ban"
    :title="__('users.dialogs.bulk_ban_title', ['count' => count($selectedIds)])"
    :description="__('users.dialogs.ban_user_desc')"
    model="bulkBanReason"
    confirm="executeBulkBan"
    :confirm-label="__('users.dialogs.ban_user')"
    cancel="$wire.cancelBulkAction()"
    :placeholder="__('users.dialogs.ban_reason_placeholder')"
/>

<x-admin.confirm-dialog
    id="bulk-unban"
    :title="__('users.dialogs.bulk_unban_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkUnban()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('users.actions.unban')"
>
    {{ __('users.dialogs.bulk_unban_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('users.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('common.delete')"
    variant="destructive"
>
    {{ __('users.dialogs.bulk_delete_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-restore"
    :title="__('users.dialogs.bulk_restore_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkRestore()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('common.restore')"
>
    {{ __('users.dialogs.bulk_restore_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-force-delete"
    :title="__('users.dialogs.bulk_force_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkForceDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('users.actions.force_delete')"
    variant="destructive"
>
    {!! __('users.dialogs.bulk_force_delete_desc') !!}
</x-admin.confirm-dialog>

{{-- ── Account deletion (grace period) ────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="schedule-deletion"
    :title="__('users.dialogs.schedule_deletion')"
    :description="__('users.dialogs.schedule_deletion_desc')"
    model="deletionReason"
    confirm="confirmScheduleDeletion"
    :confirm-label="__('users.actions.schedule_deletion')"
    variant="destructive"
    cancel="$wire.set('schedulingId', null)"
    :placeholder="__('users.dialogs.reason_optional')"
/>

<x-admin.reason-dialog
    id="instant-purge"
    :title="__('users.dialogs.instant_purge')"
    :description="__('users.dialogs.instant_purge_desc')"
    model="purgeReason"
    confirm="instantPurge"
    :confirm-label="__('users.actions.purge')"
    variant="destructive"
    cancel="$wire.set('purgingId', null)"
    :placeholder="__('users.dialogs.reason_optional')"
/>

<x-admin.reason-dialog
    id="bulk-schedule-deletion"
    :title="__('users.dialogs.bulk_schedule_deletion_title', ['count' => count($selectedIds)])"
    :description="__('users.dialogs.bulk_schedule_deletion_desc')"
    model="bulkDeletionReason"
    confirm="executeBulkScheduleDeletion"
    :confirm-label="__('users.actions.schedule_deletion')"
    variant="destructive"
    cancel="$wire.cancelBulkAction()"
    :placeholder="__('users.dialogs.reason_optional')"
/>

<x-admin.confirm-dialog
    id="bulk-stop-deletion"
    :title="__('users.dialogs.bulk_stop_deletion_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkStopDeletion()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('users.actions.stop_deletion')"
>
    {{ __('users.dialogs.bulk_stop_deletion_desc') }}
</x-admin.confirm-dialog>

<x-admin.reason-dialog
    id="bulk-instant-purge"
    :title="__('users.dialogs.bulk_instant_purge_title', ['count' => count($selectedIds)])"
    :description="__('users.dialogs.bulk_instant_purge_desc')"
    model="bulkPurgeReason"
    confirm="executeBulkInstantPurge"
    :confirm-label="__('users.actions.purge')"
    variant="destructive"
    cancel="$wire.cancelBulkAction()"
    :placeholder="__('users.dialogs.reason_optional')"
/>
