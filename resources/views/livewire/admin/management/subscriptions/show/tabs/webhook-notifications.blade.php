@php
    /** @var \Illuminate\Support\Collection<int, array{receipt: \App\Models\SubscriptionReceipt, notification: \App\Contracts\ProviderNotification}> $notifications */
    $canViewFullLog = auth()->user()->can('webhook_notifications.view');
@endphp

<div class="space-y-4">
    @forelse ($notifications as $pair)
        @php [$receipt, $notification] = [$pair['receipt'], $pair['notification']]; @endphp
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between gap-3 border-b border-border/50 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-webhook class="size-4" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-sm">{{ $receipt->provider->label() }}</x-ui.card-title>
                        <x-ui.card-description>
                            <x-ui.local-time :value="$receipt->created_at" format="MMM D, YYYY h:mm A" />
                        </x-ui.card-description>
                    </div>
                </div>
                @if ($canViewFullLog)
                    <x-ui.button variant="outline" size="sm"
                        href="{{ route('admin.webhook-notifications.show', ['provider' => $receipt->notification_provider->value, 'id' => $receipt->notification_id]) }}"
                        wire:navigate>
                        {{ __('webhook_notifications.actions.view') }}
                    </x-ui.button>
                @endif
            </x-ui.card-header>
            <x-ui.card-content>
                @include('livewire.admin.management.webhook-notifications.partials.detail', ['notification' => $notification])
            </x-ui.card-content>
        </x-ui.card>
    @empty
        <x-ui.card class="p-0">
            <div class="flex flex-col items-center justify-center gap-2 px-4 py-16 text-center text-muted-foreground">
                <x-lucide-webhook class="size-8 text-muted-foreground/30" />
                <p class="text-sm font-medium">{{ __('webhook_notifications.empty.subscription_notifications') }}</p>
            </div>
        </x-ui.card>
    @endforelse
</div>
