{{-- Bulk-action dialogs for the Tickets index. Included by index.blade —
     shares its Livewire scope ($wire, $selectedIds, etc.). --}}

<x-admin.confirm-dialog
    id="bulk-close"
    title="Close {{ count($selectedIds) }} Tickets"
    confirm="$wire.executeBulkClose()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Close"
>
    Every selected open or pending ticket will be marked Closed.
</x-admin.confirm-dialog>

{{-- Custom (not a plain confirm) — needs an agent picker, not just a description. --}}
<x-ui.dialog id="bulk-assign-tickets">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>Assign {{ count($selectedIds) }} Tickets</x-ui.dialog-title>
            <x-ui.dialog-description>Every selected ticket will be reassigned to the chosen agent.</x-ui.dialog-description>
        </x-ui.dialog-header>

        <x-ui.field>
            <x-ui.field-label for="bulkAssignAgentId" required>Agent</x-ui.field-label>
            <x-ui.select native id="bulkAssignAgentId" wire:model="bulkAssignAgentId" :options="$agentOptions" placeholder="Select an agent" />
            @error('bulkAssignAgentId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.cancelBulkAction()">Cancel</x-ui.button>
            <x-ui.button @click="open = false" wire:click="executeBulkAssign">Assign</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
