{{-- Confirmation dialogs for the Devices index. Included by index.blade — shares its Livewire scope. --}}

<x-admin.reason-dialog
    id="block-device"
    :title="__('devices.dialogs.block_title')"
    :description="__('devices.dialogs.block_description')"
    model="blockReason"
    confirm="block"
    :confirm-label="__('devices.actions.block')"
    :label="__('devices.dialogs.reason')"
    :placeholder="__('devices.placeholders.block_reason')"
    cancel="$wire.set('blockingUlid', null)"
/>

<x-admin.confirm-dialog
    id="revoke-device"
    :title="__('devices.dialogs.revoke_title')"
    confirm="$wire.revoke()"
    cancel="$wire.set('revokingUlid', null)"
    :confirm-label="__('devices.actions.revoke_short')"
    variant="destructive"
>
    {{ __('devices.dialogs.revoke_description') }}
</x-admin.confirm-dialog>
