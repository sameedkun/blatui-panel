{{-- Single-row user action dialogs, shared by the Users index and the user
     profile page. Both host components use HandlesUserRowActions, so these
     dialogs drive the same methods (and the same audit-log rows) in either place. --}}

<x-admin.reason-dialog
    id="ban-user"
    :title="__('users.dialogs.ban_user')"
    :description="__('users.dialogs.ban_user_desc')"
    model="banReason"
    confirm="confirmBan"
    :confirm-label="__('users.dialogs.ban_user')"
    cancel="$wire.set('banningUserId', null)"
    :placeholder="__('users.dialogs.ban_reason_placeholder')"
/>

<x-admin.confirm-dialog
    id="delete-user"
    :title="__('users.dialogs.delete_user')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('common.delete')"
    variant="destructive"
>
    {{ __('users.dialogs.delete_user_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    :title="__('users.dialogs.restore_user')"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    :confirm-label="__('common.restore')"
>
    {{ __('users.dialogs.restore_user_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    :title="__('users.dialogs.force_delete_user')"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    :confirm-label="__('users.actions.force_delete')"
    variant="destructive"
>
    {!! __('users.dialogs.force_delete_user_desc') !!}
</x-admin.confirm-dialog>

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
