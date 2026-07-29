{{-- Single-row confirmation dialog, shared by the Plans index and the plan
     profile page — included by both, sharing their Livewire scope. --}}

<x-admin.confirm-dialog
    id="delete-plan"
    :title="__('plans.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('plans.actions.delete')"
    variant="destructive"
>
    {{ __('plans.dialogs.delete_description') }}
</x-admin.confirm-dialog>
