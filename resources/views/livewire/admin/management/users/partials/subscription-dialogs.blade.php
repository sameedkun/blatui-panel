{{-- Subscription-management dialogs for the user profile page. Included by
     show.blade.php — shares its Livewire scope ($wire, $planOptions, etc.). --}}

{{-- ── Assign / Change plan ────────────────────────────────────────────────── --}}

<x-ui.dialog id="assign-plan">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('users.dialogs.assign_plan_title') }}</x-ui.dialog-title>
            <x-ui.dialog-description>
                {{ __('users.dialogs.assign_plan_desc') }}
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
            <x-ui.field-label required>{{ __('users.fields.plan') }}</x-ui.field-label>
            <select wire:model.live="assignPlanId" class="blat-select h-9 w-full">
                <option value="" @selected($assignPlanId === null)>{{ __('users.dialogs.select_plan') }}</option>
                @foreach ($planOptions as $id => $name)
                    <option value="{{ $id }}" @selected((int) $assignPlanId === $id)>{{ $name }}</option>
                @endforeach
            </select>
            @error('assignPlanId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label required>{{ __('subscriptions.fields.price') }}</x-ui.field-label>
            <select wire:model="assignPriceId" class="blat-select h-9 w-full">
                <option value="" disabled @selected($assignPriceId === null)>{{ __('users.dialogs.select_price') }}</option>
                @foreach ($priceOptions as $id => $label)
                    <option value="{{ $id }}" @selected((int) $assignPriceId === $id)>{{ $label }}</option>
                @endforeach
            </select>
            @error('assignPriceId')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">{{ __('common.cancel') }}</x-ui.button>
            <x-ui.button @click="open = false" wire:click="assignPlan">{{ __('common.save') }}</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>

{{-- ── Cancel immediately ──────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="cancel-immediately"
    :title="__('users.dialogs.cancel_immediately_title')"
    :description="__('users.dialogs.cancel_immediately_desc')"
    model="cancelReason"
    confirm="cancelImmediately"
    :confirm-label="__('users.actions.cancel_immediately')"
    variant="destructive"
    cancel="$wire.set('cancelReason', '')"
    :placeholder="__('users.dialogs.reason_optional')"
/>

{{-- ── Cancel at period end ────────────────────────────────────────────────── --}}

<x-admin.reason-dialog
    id="cancel-at-period-end"
    :title="__('users.dialogs.cancel_period_end_title')"
    :description="__('users.dialogs.cancel_period_end_desc')"
    model="cancelReason"
    confirm="cancelAtPeriodEnd"
    :confirm-label="__('users.actions.cancel_at_period_end')"
    variant="default"
    cancel="$wire.set('cancelReason', '')"
    :placeholder="__('users.dialogs.reason_optional')"
/>
