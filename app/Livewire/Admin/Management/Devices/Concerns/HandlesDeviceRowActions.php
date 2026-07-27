<?php

namespace App\Livewire\Admin\Management\Devices\Concerns;

use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Management\Devices\Index;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\UserDevice;
use App\Services\DeviceService;

/**
 * Single-device mutating actions (block, unblock, revoke) for the global
 * Devices {@see Index} — reaches any
 * device across any account, gated purely by the acting admin's `devices.*`
 * permissions. The per-user Devices tab on
 * {@see Show} does not use this trait —
 * it has its own scoped copy of these actions (plus revoke-all), the same
 * way that page's subscription actions are bespoke rather than reused from
 * a Subscriptions-module trait.
 *
 * Requires the using component to also use {@see HasToast}.
 */
trait HandlesDeviceRowActions
{
    public ?string $blockingUlid = null;

    public string $blockReason = '';

    public ?string $revokingUlid = null;

    protected function findDevice(string $ulid): UserDevice
    {
        return UserDevice::where('ulid', $ulid)->firstOrFail();
    }

    public function openBlockDialog(string $ulid): void
    {
        $this->authorize('devices.block');

        $this->findDevice($ulid);

        $this->blockingUlid = $ulid;
        $this->blockReason = '';
        $this->resetErrorBag('blockReason');
        $this->dispatch('open-dialog-block-device');
    }

    public function block(DeviceService $devices): void
    {
        $this->authorize('devices.block');

        $this->validate([
            'blockReason' => ['required', 'string', 'min:10'],
        ], [
            'blockReason.required' => 'A reason is required to block a device.',
            'blockReason.min' => 'The reason must be at least :min characters.',
        ]);

        $device = $this->findDevice($this->blockingUlid);
        $devices->block($device, trim($this->blockReason), auth()->user());

        $this->blockingUlid = null;
        $this->blockReason = '';
        $this->toastSuccess("{$device->displayName()} has been blocked.");
    }

    public function unblock(string $ulid, DeviceService $devices): void
    {
        $this->authorize('devices.unblock');

        $device = $this->findDevice($ulid);
        $devices->unblock($device);

        $this->toastSuccess("{$device->displayName()} has been unblocked.", 'The user must log in again to reconnect it.');
    }

    public function confirmRevoke(string $ulid): void
    {
        $this->authorize('devices.revoke');

        $this->findDevice($ulid);

        $this->revokingUlid = $ulid;
        $this->dispatch('open-alert-dialog-revoke-device');
    }

    public function revoke(DeviceService $devices): void
    {
        $this->authorize('devices.revoke');

        $device = $this->findDevice($this->revokingUlid);
        $devices->revoke($device);

        $this->revokingUlid = null;
        $this->toastSuccess("{$device->displayName()} has been revoked.");
    }
}
