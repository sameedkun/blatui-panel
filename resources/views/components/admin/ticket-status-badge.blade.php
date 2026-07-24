{{--
    Status badge for a ticket, shared across the Tickets index, the ticket
    detail header, and the conversation sidebar so the color mapping only
    lives in one place.

    Props:
      status  App\Enum\TicketStatus
--}}
@props(['status'])

@switch ($status)
    @case (\App\Enum\TicketStatus::Open)
        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-500/20 bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
            <span class="size-1.5 rounded-full bg-blue-500"></span>
            {{ $status->label() }}
        </span>
        @break
    @case (\App\Enum\TicketStatus::Pending)
        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
            <span class="size-1.5 rounded-full bg-amber-500"></span>
            {{ $status->label() }}
        </span>
        @break
    @case (\App\Enum\TicketStatus::Resolved)
        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>
            {{ $status->label() }}
        </span>
        @break
    @default
        <x-ui.badge variant="outline">{{ $status->label() }}</x-ui.badge>
@endswitch
