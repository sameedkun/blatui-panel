<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('tickets.title')" :description="__('tickets.subtitle')" :breadcrumbs="[['label' => __('tickets.common.home'), 'url' => route('admin.dashboard')], ['label' => __('tickets.title')]]">
        @can('tickets.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.tickets.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('tickets.actions.log') }}
                </x-ui.button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    {{-- Stats --}}
    @if (count($stats))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
            @endforeach
        </div>
    @endif

    {{-- Toolbar --}}
    <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        :search-placeholder="__('tickets.filters.search')" />

    {{-- Table --}}
    @php
        $canBulkAct = auth()->user()->can('tickets.manage');
        $canRowAct = auth()->user()->can('tickets.manage');
    @endphp
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="w-10 px-4 py-3 text-left">
                        @if ($canBulkAct)
                            <input type="checkbox" x-data
                                :checked="@js($pageIds).length > 0 && @js($pageIds).every(id => $wire
                                    .selectedIds.includes(id))"
                                @change="$wire.toggleSelectPage(@js($pageIds))"
                                class="blat-checkbox cursor-pointer dark:bg-input/30" />
                        @endif
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('subject')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('tickets.fields.ticket') }}
                            @if ($sortBy === 'subject')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground sm:table-cell">{{ __('tickets.fields.category') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('tickets.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('tickets.fields.priority') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">{{ __('tickets.fields.agent') }}</th>
                    <th class="hidden px-4 py-3 text-left xl:table-cell">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('tickets.fields.created') }}
                            @if ($sortBy === 'created_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($tickets as $ticket)
                    <tr wire:key="ticket-row-{{ $ticket->id }}"
                        class="hover:bg-muted/30 {{ in_array((string) $ticket->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkAct)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $ticket->id }}')"
                                    @change="$wire.toggleSelection('{{ $ticket->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Ticket --}}
                        <td class="px-4 py-3">
                            @can('tickets.manage')
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-medium hover:underline">{{ $ticket->subject }}</a>
                            @else
                                <span class="font-medium">{{ $ticket->subject }}</span>
                            @endcan
                            <div class="text-xs text-muted-foreground">#{{ $ticket->id }} &bull; {{ $ticket->user?->name ?? __('tickets.common.unknown') }}</div>
                        </td>

                        {{-- Category --}}
                        <td class="hidden px-4 py-3 sm:table-cell">
                            @if ($ticket->category)
                                <x-ui.badge variant="secondary">{{ $ticket->category->name }}</x-ui.badge>
                            @else
                                <span class="text-xs text-muted-foreground">{{ __('tickets.common.none') }}</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            <x-admin.ticket-status-badge :status="$ticket->status" />
                        </td>

                        {{-- Priority --}}
                        <td class="hidden px-4 py-3 md:table-cell">
                            <x-admin.ticket-priority-badge :priority="$ticket->priority" />
                        </td>

                        {{-- Agent --}}
                        <td class="hidden px-4 py-3 lg:table-cell">
                            @if ($ticket->agent)
                                <span class="text-xs font-medium">{{ $ticket->agent->name }}</span>
                            @else
                                <span class="text-xs text-muted-foreground italic">{{ __('tickets.unassigned') }}</span>
                            @endif
                        </td>

                        {{-- Created --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                            <x-ui.local-time :value="$ticket->created_at" show-diff="true" />
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('tickets.manage')
                                    <x-admin.tooltip :text="__('tickets.actions.view')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.tickets.show', $ticket) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">{{ __('tickets.actions.view') }}</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan

                                @if ($canRowAct)
                                    <x-admin.dropdown align="end" width="w-48">
                                        <x-slot:trigger>
                                            <x-ui.button variant="ghost" size="icon" class="size-8">
                                                <x-lucide-ellipsis class="size-4" />
                                                <span class="sr-only">{{ __('tickets.common.actions') }}</span>
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        @if (! $ticket->agent || $ticket->agent->isNot(auth()->user()))
                                            <x-admin.dropdown-item @click="$wire.assignToMe({{ $ticket->id }})">
                                                <x-lucide-user-check class="size-4" />
                                                {{ __('tickets.actions.assign_me') }}
                                            </x-admin.dropdown-item>
                                        @endif

                                        @if ($ticket->status !== \App\Enum\TicketStatus::Closed)
                                            <x-admin.dropdown-item @click="$wire.close({{ $ticket->id }})">
                                                <x-lucide-circle-check class="size-4" />
                                                {{ __('tickets.actions.close_short') }}
                                            </x-admin.dropdown-item>
                                        @else
                                            <x-admin.dropdown-item @click="$wire.reopen({{ $ticket->id }})">
                                                <x-lucide-rotate-ccw class="size-4" />
                                                {{ __('tickets.actions.reopen_short') }}
                                            </x-admin.dropdown-item>
                                        @endif
                                    </x-admin.dropdown>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-life-buoy class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('tickets.empty.tickets') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('tickets.common.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$tickets" />

    {{-- Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('tickets.common.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ __('tickets.common.selected', ['count' => count($selectedIds)]) }}</span>
            <div class="mx-1 h-4 w-px bg-border"></div>

            @foreach ($this->availableBulkActions as $action)
                @can($action['permission'])
                    <x-admin.tooltip :text="$action['label']">
                        <x-ui.button variant="{{ $action['variant'] ?? 'ghost' }}" size="icon"
                            class="size-8 rounded-full"
                            wire:click="{{ $action['confirm'] ? 'openBulkConfirm(\'' . $action['key'] . '\')' : 'executeBulk' . str_replace('-', '', ucwords($action['key'], '-')) . '()' }}">
                            <x-dynamic-component :component="'lucide-' . $action['icon']" class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                @endcan
            @endforeach
        </div>
    @endif

    {{-- ── Dialogs ──────────────────────────────────────────────────────── --}}
    @include('livewire.admin.support.tickets.partials.dialogs')

</div>
