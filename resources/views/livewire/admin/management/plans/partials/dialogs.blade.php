{{-- Confirmation dialogs for the Plans index. Included by index.blade — shares its
     Livewire scope ($wire, $selectedIds, etc.). --}}

{{-- ── Single-row (shared with the plan profile page) ─────────────────────── --}}

@include('livewire.admin.management.plans.partials.single-row-dialogs')

{{-- ── Bulk ───────────────────────────────────────────────────────────────── --}}

<x-admin.confirm-dialog
    id="bulk-activate"
    :title="__('plans.dialogs.bulk_activate_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkActivate()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('plans.actions.activate')"
>
    {{ __('plans.dialogs.bulk_activate_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-deactivate"
    :title="__('plans.dialogs.bulk_deactivate_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDeactivate()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('plans.actions.deactivate')"
>
    {{ __('plans.dialogs.bulk_deactivate_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('plans.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('plans.actions.delete')"
    variant="destructive"
>
    {{ __('plans.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>
