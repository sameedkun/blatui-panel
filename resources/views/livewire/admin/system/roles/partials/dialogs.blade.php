{{-- Confirmation dialogs for the Roles index. Included by index.blade — shares its
     Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-role"
    title="Delete Role"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will permanently delete the role and its permission assignments.
    @if ($deletingStaffCount > 0)
        <strong>{{ $deletingStaffCount }} staff {{ $deletingStaffCount === 1 ? 'member' : 'members' }}</strong>
        currently {{ $deletingStaffCount === 1 ? 'holds' : 'hold' }} this role and will lose the permissions it grants.
    @endif
</x-admin.confirm-dialog>
