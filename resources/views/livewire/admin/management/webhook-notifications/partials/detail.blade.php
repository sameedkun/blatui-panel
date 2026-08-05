{{--
    Generic detail view for one raw provider webhook notification. Renders
    only the {@see \App\Contracts\ProviderNotification} contract plus two
    optional presentation hooks (`notificationTypeLabel()`, `subtypeLabel()`)
    discovered via method_exists() — nothing here branches on which provider
    it's rendering, so a new provider needs no new Blade.

    Expects:
        $notification   \App\Contracts\ProviderNotification
--}}
@php
    use Illuminate\Support\Str;

    $typeLabel = method_exists($notification, 'notificationTypeLabel')
        ? $notification->notificationTypeLabel()
        : Str::headline($notification->notificationType());

    $subtypeLabel = method_exists($notification, 'subtypeLabel') ? $notification->subtypeLabel() : null;
@endphp

<div class="space-y-6">
    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.notification_type') }}</dt>
            <dd class="font-medium"><x-ui.badge variant="secondary">{{ $typeLabel }}</x-ui.badge></dd>
        </div>

        @if ($subtypeLabel)
            <div>
                <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.subtype') }}</dt>
                <dd class="font-medium"><x-ui.badge variant="outline">{{ $subtypeLabel }}</x-ui.badge></dd>
            </div>
        @endif

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.transaction_id') }}</dt>
            <dd class="font-mono text-xs select-all">{{ $notification->transactionId() ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.original_transaction_id') }}</dt>
            <dd class="font-mono text-xs select-all">{{ $notification->originalTransactionId() ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.product_id') }}</dt>
            <dd class="font-mono text-xs">{{ $notification->productId() ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.environment') }}</dt>
            <dd class="font-medium">{{ $notification->environment() ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.occurred_at') }}</dt>
            <dd class="font-medium">
                @if ($notification->occurredAt())
                    <x-ui.local-time :value="$notification->occurredAt()" format="MMM D, YYYY h:mm A" />
                @else
                    —
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs text-muted-foreground">{{ __('webhook_notifications.detail.processed_at') }}</dt>
            <dd>
                @if ($notification->isProcessed())
                    <x-ui.badge class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                        {{ __('webhook_notifications.values.processed') }}
                    </x-ui.badge>
                    @if ($notification->processedAt())
                        <span class="ml-1 text-xs text-muted-foreground">
                            <x-ui.local-time :value="$notification->processedAt()" format="MMM D, YYYY h:mm A" />
                        </span>
                    @endif
                @else
                    <x-ui.badge variant="outline">{{ __('webhook_notifications.values.unprocessed') }}</x-ui.badge>
                @endif
            </dd>
        </div>
    </dl>

    <div>
        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">{{ __('webhook_notifications.detail.raw_payload') }}</p>
        <pre class="max-h-96 overflow-auto rounded-md border border-border bg-muted/20 p-3 text-xs">{{ json_encode($notification->rawPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</div>
