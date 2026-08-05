@php
    /** @var \App\Contracts\ProviderNotification $notification */
@endphp

<div class="flex flex-col gap-6">

    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()">
        <x-slot:actions>
            <x-ui.badge variant="secondary">{{ $providerLabel }}</x-ui.badge>

            @can('webhook_notifications.manage')
                @if ($notification instanceof \App\Contracts\RedispatchableNotification)
                    <x-ui.button variant="outline" size="sm" wire:click="confirmRedispatch('{{ $provider }}', {{ $record->getKey() }})">
                        <x-lucide-refresh-cw class="size-4" />
                        {{ $notification->isProcessed() ? __('webhook_notifications.actions.reprocess') : __('webhook_notifications.actions.process') }}
                    </x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.card>
        <x-ui.card-content>
            @include('livewire.admin.management.webhook-notifications.partials.detail', ['notification' => $notification])
        </x-ui.card-content>
    </x-ui.card>

    {{-- Confirmation dialog --}}
    <x-admin.confirm-dialog
        id="redispatch-webhook-notification"
        :title="__('webhook_notifications.dialogs.redispatch_title')"
        confirm="$wire.redispatch()"
        cancel="$wire.set('redispatchProvider', null)"
        :confirm-label="__('webhook_notifications.actions.reprocess')"
    >
        {{ __('webhook_notifications.dialogs.redispatch_description') }}
    </x-admin.confirm-dialog>

</div>
