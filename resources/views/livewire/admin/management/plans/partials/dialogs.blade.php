{{-- Confirmation dialogs for the Plans index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the plan profile page) ─────────────────────── --}}

@include('livewire.admin.management.plans.partials.single-row-dialogs')

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.confirm-dialog
    id="bulk-activate"
    title="Activate {{ count($selectedIds) }} Plans"
    confirm="$wire.executeBulkActivate()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Activate"
>
    All selected plans will become visible for purchase.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-deactivate"
    title="Deactivate {{ count($selectedIds) }} Plans"
    confirm="$wire.executeBulkDeactivate()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Deactivate"
>
    All selected plans will be hidden from purchase. Existing subscriptions are unaffected.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Plans"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    Plans with active subscriptions will be skipped — deactivate those instead. This action <strong>cannot be undone</strong> for the rest.
</x-admin.confirm-dialog>
