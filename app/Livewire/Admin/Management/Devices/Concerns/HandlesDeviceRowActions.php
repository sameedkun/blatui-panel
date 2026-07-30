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
            'blockReason.required' => __('devices.validation.block_reason_required'),
            'blockReason.min' => __('devices.validation.block_reason_min'),
        ]);

        $device = $this->findDevice($this->blockingUlid);
        $devices->block($device, trim($this->blockReason), auth()->user());

        $this->blockingUlid = null;
        $this->blockReason = '';
        $this->dispatch('close-dialog-block-device');
        $this->toastSuccess(__('devices.toasts.blocked', ['name' => $device->displayName()]));
    }

    public function unblock(string $ulid, DeviceService $devices): void
    {
        $this->authorize('devices.unblock');

        $device = $this->findDevice($ulid);
        $devices->unblock($device);

        $this->toastSuccess(
            __('devices.toasts.unblocked', ['name' => $device->displayName()]),
            __('devices.toasts.unblocked_description'),
        );
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
        $this->toastSuccess(__('devices.toasts.revoked', ['name' => $device->displayName()]));
    }
}
