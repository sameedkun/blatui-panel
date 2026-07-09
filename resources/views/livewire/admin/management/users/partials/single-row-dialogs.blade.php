{{-- Single-row user action dialogs, shared by the Users index and the user
     profile page. Both host components use HandlesUserRowActions, so these
     dialogs drive the same methods (and the same audit-log rows) in either place. --}}

<x-admin.reason-dialog
    id="ban-user"
    title="Ban User"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="banReason"
    confirm="confirmBan"
    confirm-label="Ban User"
    cancel="$wire.set('banningUserId', null)"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="delete-user"
    title="Delete User"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will soft-delete the user. They can be restored later.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    title="Restore User"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    confirm-label="Restore"
>
    This will restore the user's account.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    title="Permanently Delete"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. The user and all associated data will be permanently removed.
</x-admin.confirm-dialog>

<x-admin.reason-dialog
    id="schedule-deletion"
    title="Schedule Account Deletion"
    description="The account stays live during the grace period, then is permanently purged. You can stop it before then."
    model="deletionReason"
    confirm="confirmScheduleDeletion"
    confirm-label="Schedule Deletion"
    variant="destructive"
    cancel="$wire.set('schedulingId', null)"
    placeholder="Reason for deletion (optional)"
/>

<x-admin.reason-dialog
    id="instant-purge"
    title="Purge Account Now"
    description="This permanently deletes the account immediately, skipping the grace period."
    model="purgeReason"
    confirm="instantPurge"
    confirm-label="Purge Permanently"
    variant="destructive"
    cancel="$wire.set('purgingId', null)"
    placeholder="Reason (optional)"
/>
