{{-- Single-row confirmation dialog, shared by the Plans index and the plan
     profile page — included by both, sharing their Livewire scope. --}}

<x-admin.confirm-dialog
    id="delete-plan"
    title="Delete Plan"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This permanently deletes the plan and all of its prices and payment provider mappings. This action <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>
