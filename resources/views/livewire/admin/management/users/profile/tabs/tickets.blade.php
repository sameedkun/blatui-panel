@php
    /** @var \App\Models\User $record */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $ticketHistory */
@endphp

<div class="flex flex-col gap-6">

    <x-ui.card class="p-0 overflow-hidden">
        <div class="border-b border-border/50 p-4">
            <h3 class="text-sm font-semibold text-foreground">Support Tickets</h3>
            <p class="text-xs text-muted-foreground">Tickets opened by {{ $record->name }}, newest first.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <th class="px-4 py-3 text-left font-semibold">Ticket</th>
                        <th class="hidden px-4 py-3 text-left font-semibold sm:table-cell">Category</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">Priority</th>
                        <th class="hidden px-4 py-3 text-left font-semibold lg:table-cell">Agent</th>
                        <th class="hidden px-4 py-3 text-left font-semibold xl:table-cell">Created</th>
                        @can('tickets.manage')
                            <th class="w-10 px-4 py-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse ($ticketHistory as $ticket)
                        <tr wire:key="user-ticket-{{ $ticket->id }}" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3.5">
                                @can('tickets.manage')
                                    <a href="{{ route('admin.tickets.show', $ticket) }}"
                                        class="inline-flex items-center gap-1 font-semibold text-foreground hover:text-primary transition-colors group">
                                        <span>{{ $ticket->subject }}</span>
                                        <x-lucide-arrow-up-right
                                            class="size-3.5 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                    </a>
                                @else
                                    <span class="font-semibold text-foreground">{{ $ticket->subject }}</span>
                                @endcan
                                <p class="text-xs text-muted-foreground">#{{ $ticket->id }}</p>
                            </td>
                            <td class="hidden px-4 py-3.5 sm:table-cell">
                                @if ($ticket->category)
                                    <x-ui.badge variant="secondary">{{ $ticket->category->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">None</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <x-admin.ticket-status-badge :status="$ticket->status" />
                            </td>
                            <td class="hidden px-4 py-3.5 md:table-cell">
                                <x-admin.ticket-priority-badge :priority="$ticket->priority" />
                            </td>
                            <td class="hidden px-4 py-3.5 lg:table-cell">
                                @if ($ticket->agent)
                                    <span class="text-xs font-medium">{{ $ticket->agent->name }}</span>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3.5 text-xs text-muted-foreground xl:table-cell">
                                <x-ui.local-time :value="$ticket->created_at" />
                            </td>
                            @can('tickets.manage')
                                <td class="px-4 py-3.5 text-right">
                                    <x-admin.tooltip text="View ticket">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.tickets.show', $ticket) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">View ticket</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-lucide-life-buoy class="size-8 text-muted-foreground/30" />
                                    <p class="text-sm font-medium">No support tickets.</p>
                                    <p class="text-xs">{{ $record->name }} hasn't opened any tickets yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($ticketHistory->hasPages())
            <div class="border-t border-border p-4">
                {{ $ticketHistory->links('livewire.admin.partials.pagination') }}
            </div>
        @endif
    </x-ui.card>

</div>
