{{-- Bulk-action dialogs for the Tickets index. Included by index.blade —
     shares its Livewire scope ($wire, $selectedIds, etc.). --}}

<x-admin.confirm-dialog
    id="bulk-close"
    :title="__('tickets.dialogs.close_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkClose()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('tickets.actions.close_short')"
>
    {{ __('tickets.dialogs.close_description') }}
</x-admin.confirm-dialog>

{{-- Custom (not a plain confirm) — needs an agent picker, not just a description. --}}
<x-ui.dialog id="bulk-assign-tickets">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('tickets.dialogs.assign_title', ['count' => count($selectedIds)]) }}</x-ui.dialog-title>
            <x-ui.dialog-description>{{ __('tickets.dialogs.assign_description') }}</x-ui.dialog-description>
        </x-ui.dialog-header>

        <x-ui.field>
            <x-ui.field-label for="bulkAssignAgentId" required>{{ __('tickets.fields.agent') }}</x-ui.field-label>
            <x-ui.select native id="bulkAssignAgentId" wire:model="bulkAssignAgentId" :options="$agentOptions" :placeholder="__('tickets.dialogs.select_agent')" />
            @error('bulkAssignAgentId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.cancelBulkAction()">{{ __('tickets.common.cancel') }}</x-ui.button>
            <x-ui.button @click="open = false" wire:click="executeBulkAssign">{{ __('tickets.actions.assign') }}</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
