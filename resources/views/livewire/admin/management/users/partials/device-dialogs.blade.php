{{-- Device-management dialogs for the user profile page. Included by
     show.blade.php — shares its Livewire scope ($wire, $record). --}}

<x-admin.reason-dialog
    id="block-user-device"
    :title="__('users.dialogs.block_device_title')"
    :description="__('users.dialogs.block_device_desc')"
    model="blockDeviceReason"
    confirm="blockDevice"
    :confirm-label="__('users.actions.block_device')"
    :label="__('common.reason')"
    :placeholder="__('users.dialogs.block_device_reason_placeholder')"
    cancel="$wire.set('blockingDeviceUlid', null)"
    :close-on-confirm="false"
/>

<x-admin.confirm-dialog
    id="revoke-user-device"
    :title="__('users.dialogs.revoke_device_title')"
    confirm="$wire.revokeDevice()"
    cancel="$wire.set('revokingDeviceUlid', null)"
    :confirm-label="__('users.actions.revoke_device')"
    variant="destructive"
>
    {{ __('users.dialogs.revoke_device_desc') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="revoke-all-user-devices"
    :title="__('users.dialogs.revoke_all_devices_title')"
    confirm="$wire.revokeAllDevices()"
    cancel="$wire.set('revokingAllDevices', false)"
    :confirm-label="__('users.actions.revoke_all_devices')"
    variant="destructive"
>
    {{ __('users.dialogs.revoke_all_devices_desc') }}
</x-admin.confirm-dialog>
