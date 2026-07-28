{{-- Single-row guest action dialogs, shared by the Guests index and the guest
     profile page. Both host components use HandlesGuestRowActions, so these
     dialogs drive the same methods (and the same audit-log rows) in either place. --}}

<x-admin.reason-dialog
    id="ban-user"
    :title="__('guests.dialogs.ban_guest')"
    :description="__('guests.dialogs.ban_guest_desc')"
    model="banReason"
    confirm="confirmBan"
    :confirm-label="__('guests.dialogs.ban_guest')"
    cancel="$wire.set('banningUserId', null)"
    :placeholder="__('guests.dialogs.ban_reason_placeholder')"
/>

<x-admin.confirm-dialog
    id="delete-user"
    :title="__('guests.dialogs.delete_guest')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('common.force_delete')"
    variant="destructive"
>
    {{ __('guests.dialogs.delete_guest_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    :title="__('guests.dialogs.restore_guest')"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    :confirm-label="__('common.restore')"
>
    {{ __('guests.dialogs.restore_guest_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    :title="__('guests.dialogs.force_delete_guest')"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    :confirm-label="__('common.force_delete')"
    variant="destructive"
>
    {{ __('guests.dialogs.force_delete_guest_desc') }}
</x-admin.confirm-dialog>
