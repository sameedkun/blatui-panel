@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $receipts */
@endphp

<x-ui.card class="p-0 overflow-hidden">
    <div class="border-b border-border/50 p-4">
        <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                <x-lucide-receipt class="size-4" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-foreground">Provider Receipts</h3>
                <p class="text-xs text-muted-foreground">Webhooks and transaction events recorded for this subscription.</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <th class="px-4 py-3 text-left font-semibold">Event Type</th>
                    <th class="px-4 py-3 text-left font-semibold">Gateway</th>
                    <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">Transaction ID</th>
                    <th class="hidden px-4 py-3 text-left font-semibold lg:table-cell">Original ID</th>
                    <th class="px-4 py-3 text-right font-semibold">Recorded Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/60">
                @forelse ($receipts as $receipt)
                    <tr wire:key="receipt-{{ $receipt->id }}" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <x-ui.badge variant="secondary" class="text-xs font-medium">{{ $receipt->type->label() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3.5 font-medium text-foreground">{{ $receipt->provider->label() }}</td>
                        <td class="hidden px-4 py-3.5 font-mono text-xs text-muted-foreground select-all md:table-cell">
                            {{ $receipt->provider_transaction_id ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-3.5 font-mono text-xs text-muted-foreground select-all lg:table-cell">
                            {{ $receipt->provider_original_id ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right text-xs text-muted-foreground">
                            <x-ui.local-time :value="$receipt->created_at" format="MMM D, YYYY h:mm A" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <x-lucide-receipt class="size-8 text-muted-foreground/30" />
                                <p class="text-sm font-medium">No provider receipts recorded for this subscription yet.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($receipts->hasPages())
        <div class="border-t border-border p-4">
            {{ $receipts->links('livewire.admin.partials.pagination') }}
        </div>
    @endif
</x-ui.card>
