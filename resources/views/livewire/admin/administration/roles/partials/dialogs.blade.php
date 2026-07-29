{{-- Confirmation dialogs for the Roles index. Included by index.blade — shares its
     Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-role"
    :title="__('roles.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('roles.actions.delete')"
    variant="destructive"
>
    {{ __('roles.dialogs.delete_description') }}
    @if ($deletingStaffCount > 0)
        <strong>{{ trans_choice('roles.dialogs.staff_affected', $deletingStaffCount, ['count' => $deletingStaffCount]) }}</strong>
    @endif
</x-admin.confirm-dialog>
