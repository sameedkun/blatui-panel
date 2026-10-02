@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\SubscriptionTransaction> $transactions */
    $canViewNotifications = auth()->user()->can('webhook_notifications.view');
@endphp

<x-ui.card class="p-0 overflow-hidden">
    <div class="border-b border-border/50 p-4">
        <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                <x-lucide-receipt class="size-4" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ __('subscriptions.transactions.title') }}</h3>
                <p class="text-xs text-muted-foreground">{{ __('subscriptions.transactions.description') }}</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <th class="px-4 py-3 text-left font-semibold">{{ __('subscriptions.transactions.type') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('subscriptions.transactions.amount') }}</th>
                    <th class="hidden px-4 py-3 text-left font-semibold sm:table-cell">{{ __('subscriptions.transactions.gateway') }}</th>
                    <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">{{ __('subscriptions.transactions.transaction_id') }}</th>
                    <th class="hidden px-4 py-3 text-left font-semibold lg:table-cell">{{ __('subscriptions.transactions.original_id') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('subscriptions.transactions.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/60">
                @forelse ($transactions as $transaction)
                    @php
                        $signed = $transaction->signedMoney();
                    @endphp
                    <tr wire:key="transaction-{{ $transaction->id }}" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <x-ui.badge variant="secondary" class="text-xs font-medium">{{ $transaction->type->label() }}</x-ui.badge>
                        </td>
                        <td @class([
                            'px-4 py-3.5 text-right font-medium tabular-nums',
                            'text-destructive' => $signed && $signed->minor < 0,
                            'text-foreground' => ! $signed || $signed->minor >= 0,
                        ])>
                            {{ $signed?->format() ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-3.5 font-medium text-foreground sm:table-cell">{{ $transaction->provider->label() }}</td>
                        <td class="hidden px-4 py-3.5 font-mono text-xs text-muted-foreground md:table-cell">
                            <span class="select-all">{{ $transaction->provider_transaction_id ?? '—' }}</span>
                            @if ($canViewNotifications && $transaction->notification_provider && $transaction->notification_id)
                                <a href="{{ route('admin.webhook-notifications.show', ['provider' => $transaction->notification_provider->value, 'id' => $transaction->notification_id]) }}"
                                    wire:navigate
                                    class="mt-0.5 flex items-center gap-1 text-[11px] text-primary hover:underline">
                                    <x-lucide-webhook class="size-3" />
                                    {{ __('subscriptions.transactions.view_notification') }}
                                </a>
                            @endif
                        </td>
                        <td class="hidden px-4 py-3.5 font-mono text-xs text-muted-foreground select-all lg:table-cell">
                            {{ $transaction->provider_original_id ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right text-xs text-muted-foreground">
                            <x-ui.local-time :value="$transaction->purchased_at ?? $transaction->created_at" format="MMM D, YYYY h:mm A" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <x-lucide-receipt class="size-8 text-muted-foreground/30" />
                                <p class="text-sm font-medium">{{ __('subscriptions.transactions.none') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($transactions->hasPages())
        <div class="border-t border-border p-4">
            {{ $transactions->links('livewire.admin.partials.pagination') }}
        </div>
    @endif
</x-ui.card>
