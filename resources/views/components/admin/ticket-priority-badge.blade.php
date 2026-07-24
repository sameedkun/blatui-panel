{{--
    Priority badge for a ticket. Props: priority (App\Enum\TicketPriority).
--}}
@props(['priority'])

@switch ($priority)
    @case (\App\Enum\TicketPriority::Urgent)
        <x-ui.badge variant="destructive" class="gap-1">
            <x-lucide-triangle-alert class="size-3" />
            {{ $priority->label() }}
        </x-ui.badge>
        @break
    @case (\App\Enum\TicketPriority::High)
        <span class="inline-flex items-center gap-1.5 rounded-full border border-orange-500/20 bg-orange-500/10 px-2.5 py-0.5 text-xs font-medium text-orange-600 dark:text-orange-400">
            {{ $priority->label() }}
        </span>
        @break
    @case (\App\Enum\TicketPriority::Medium)
        <x-ui.badge variant="secondary">{{ $priority->label() }}</x-ui.badge>
        @break
    @default
        <x-ui.badge variant="outline">{{ $priority->label() }}</x-ui.badge>
@endswitch
