{{-- Subscription-management dialogs for the user profile page. Included by
     show.blade.php — shares its Livewire scope ($wire, $planOptions, etc.). --}}

{{-- ── Assign / Change plan ────────────────────────────────────────────────── --}}

<x-ui.dialog id="assign-plan">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>Assign / Change Plan</x-ui.dialog-title>
            <x-ui.dialog-description>
                Manually assigns this user to a plan and price. Any existing active subscription is replaced (prorated if upgrading).
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        {{--
            Written as raw <select>/<option> tags rather than <x-ui.select :options="...">
            on purpose: that component's native-mode normalization treats integer array
            keys as a plain value-list shorthand (value === label) and silently drops
            the real key, so it would submit the plan/price *name* string instead of its
            id — a TypeError against these ?int properties. Plain options sidestep it.
        --}}
        <x-ui.field>
            <x-ui.field-label required>Plan</x-ui.field-label>
            <select wire:model.live="assignPlanId" class="blat-select h-9 w-full">
                <option value="" @selected($assignPlanId === null)>Select a plan</option>
                @foreach ($planOptions as $id => $name)
                    <option value="{{ $id }}" @selected((int) $assignPlanId === $id)>{{ $name }}</option>
                @endforeach
            </select>
            @error('assignPlanId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label required>Price</x-ui.field-label>
            <select wire:model="assignPriceId" class="blat-select h-9 w-full">
                <option value="" disabled @selected($assignPriceId === null)>Select a price</option>
                @foreach ($priceOptions as $id => $label)
                    <option value="{{ $id }}" @selected((int) $assignPriceId === $id)>{{ $label }}</option>
                @endforeach
            </select>
            @error('assignPriceId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">Cancel</x-ui.button>
            <x-ui.button @click="open = false" wire:click="assignPlan">Save</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>

{{-- ── Cancel immediately ──────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="cancel-immediately"
    title="Cancel Subscription Immediately"
    description="Access ends right away — the plan's features stop applying to this account as soon as you confirm."
    model="cancelReason"
    confirm="cancelImmediately"
    confirm-label="Cancel Immediately"
    variant="destructive"
    cancel="$wire.set('cancelReason', '')"
    placeholder="Reason (optional)"
/>

{{-- ── Cancel at period end ────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="cancel-at-period-end"
    title="Cancel at Period End"
    description="Auto-renew turns off, but the user keeps full access until the current billing period ends."
    model="cancelReason"
    confirm="cancelAtPeriodEnd"
    confirm-label="Cancel at Period End"
    variant="default"
    cancel="$wire.set('cancelReason', '')"
    placeholder="Reason (optional)"
/>
