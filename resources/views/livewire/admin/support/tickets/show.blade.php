<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()" :back="route('admin.tickets.index')" />

    {{-- Hero Ticket Command Banner --}}
    <div class="relative overflow-hidden rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="pointer-events-none absolute -right-12 -top-12 size-56 rounded-full bg-primary/5 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-br from-primary/15 via-primary/10 to-primary/5 text-primary shadow-xs">
                    <x-lucide-life-buoy class="size-6" />
                </div>
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-bold tracking-tight text-foreground">{{ $record->subject }}</h1>
                        <x-admin.ticket-status-badge :status="$record->status" />
                        <x-admin.ticket-priority-badge :priority="$record->priority" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ __('tickets.show.raised_by', ['name' => $record->user?->name ?? __('tickets.common.unknown')]) }}
                        &bull; <x-ui.local-time :value="$record->created_at" :format="__('tickets.show.date_format')" />
                        &bull; {{ __('tickets.show.ticket_number', ['id' => $record->id]) }}
                    </p>
                </div>
            </div>

            {{-- Header Actions --}}
            @can('tickets.manage')
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if (! $record->agent || $record->agent->isNot(auth()->user()))
                        <x-ui.button size="sm" variant="outline" wire:click="assignToMe({{ $record->id }})" class="gap-1.5 shadow-2xs">
                            <x-lucide-user-check class="size-3.5" />
                            <span>{{ __('tickets.actions.assign_me') }}</span>
                        </x-ui.button>
                    @endif

                    @if ($record->status !== \App\Enum\TicketStatus::Closed)
                        <x-ui.button size="sm" variant="outline" wire:click="close({{ $record->id }})" class="gap-1.5 shadow-2xs">
                            <x-lucide-circle-check class="size-3.5 text-emerald-500" />
                            <span>{{ __('tickets.actions.close') }}</span>
                        </x-ui.button>
                    @else
                        <x-ui.button size="sm" variant="ghost" wire:click="reopen({{ $record->id }})" class="gap-1.5 shadow-2xs">
                            <x-lucide-rotate-ccw class="size-3.5" />
                            <span>{{ __('tickets.actions.reopen') }}</span>
                        </x-ui.button>
                    @endif
                </div>
            @endcan
        </div>
    </div>

    {{-- Tabs + active tab content --}}
    <x-admin.show-tabs :tabs="$this->availableTabs()" :active="$this->resolveActiveTab()">
        @if ($this->activeTabView())
            @include($this->activeTabView(), array_merge(['record' => $record, 'show' => $this], $this->activeTabData()))
        @endif
    </x-admin.show-tabs>

</div>
