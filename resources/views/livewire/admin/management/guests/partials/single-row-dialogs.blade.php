{{-- Single-row guest action dialogs, shared by the Guests index and the guest
     profile page. Both host components use HandlesGuestRowActions, so these
     dialogs drive the same methods (and the same audit-log rows) in either place. --}}

<x-admin.reason-dialog
    id="ban-user"
    title="Ban Guest"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="banReason"
    confirm="confirmBan"
    confirm-label="Ban Guest"
    cancel="$wire.set('banningUserId', null)"
    placeholder="Reason for the ban (optional)"
/>

<x-admin.confirm-dialog
    id="delete-user"
    title="Delete Guest"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This permanently deletes the guest and all associated data. This <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    title="Restore Guest"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    confirm-label="Restore"
>
    This will restore the guest's account.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    title="Permanently Delete"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. The guest and all associated data will be permanently removed.
</x-admin.confirm-dialog>
