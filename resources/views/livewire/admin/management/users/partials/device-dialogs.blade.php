{{-- Device-management dialogs for the user profile page. Included by
     show.blade.php — shares its Livewire scope ($wire, $record). --}}

<x-admin.reason-dialog
    id="block-user-device"
    title="Block Device"
    description="A blocked device is signed out immediately and can never be reactivated by logging in again — only an admin can lift the block."
    model="blockDeviceReason"
    confirm="blockDevice"
    confirm-label="Block Device"
    label="Reason"
    placeholder="Why is this device being blocked?"
    cancel="$wire.set('blockingDeviceUlid', null)"
/>

<x-admin.confirm-dialog
    id="revoke-user-device"
    title="Revoke Device"
    confirm="$wire.revokeDevice()"
    cancel="$wire.set('revokingDeviceUlid', null)"
    confirm-label="Revoke"
    variant="destructive"
>
    This immediately signs the device out. It can log back in again later, subject to the account's device limit.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="revoke-all-user-devices"
    title="Revoke All Devices"
    confirm="$wire.revokeAllDevices()"
    cancel="$wire.set('revokingAllDevices', false)"
    confirm-label="Revoke All"
    variant="destructive"
>
    This immediately signs out all <strong>{{ $this->activeDeviceCount() }}</strong> active
    {{ str('device')->plural($this->activeDeviceCount()) }} on {{ $record->name }}'s account.
</x-admin.confirm-dialog>
