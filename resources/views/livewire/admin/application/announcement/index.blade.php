<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('announcements.title')" :description="__('announcements.subtitle')" :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('announcements.title')]]">
        @can('announcements.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.announcements.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('announcements.actions.create') }}
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
        :search-placeholder="__('announcements.filters.search')" />

    {{-- Table --}}
    @php
        $canBulkDelete = auth()->user()->can('announcements.delete');
        $canRowAct = auth()->user()->canAny(['announcements.edit', 'announcements.delete']);
    @endphp
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="w-10 px-4 py-3 text-left">
                        @if ($canBulkDelete)
                            <input type="checkbox" x-data
                                :checked="@js($pageIds).length > 0 && @js($pageIds).every(id => $wire
                                    .selectedIds.includes(id))"
                                @change="$wire.toggleSelectPage(@js($pageIds))"
                                class="blat-checkbox cursor-pointer dark:bg-input/30" />
                        @endif
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('title')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('announcements.fields.announcement') }}
                            @if ($sortBy === 'title')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground sm:table-cell">{{ __('announcements.fields.type') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('announcements.fields.push_status') }}</th>
                    <th class="hidden px-4 py-3 text-left md:table-cell">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('announcements.fields.created') }}
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
                @forelse ($announcements as $item)
                    <tr wire:key="announcement-row-{{ $item->id }}"
                        class="hover:bg-muted/30 {{ in_array((string) $item->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkDelete)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $item->id }}')"
                                    @change="$wire.toggleSelection('{{ $item->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Announcement --}}
                        <td class="px-4 py-3">
                            <span class="truncate font-medium">{{ $item->title }}</span>
                            <div class="line-clamp-1 max-w-md text-xs text-muted-foreground">{{ $item->message }}</div>
                        </td>

                        {{-- Type --}}
                        <td class="hidden px-4 py-3 sm:table-cell">
                            <x-ui.badge variant="secondary">{{ $item->type->label() }}</x-ui.badge>
                        </td>

                        {{-- Push status --}}
                        <td class="px-4 py-3">
                            @if ($item->push_status === \App\Enum\AnnouncementPushStatus::Sent)
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $item->push_status->label() }}</x-ui.badge>
                            @elseif ($item->push_status === \App\Enum\AnnouncementPushStatus::Failed)
                                <x-ui.badge variant="destructive">{{ $item->push_status->label() }}</x-ui.badge>
                            @elseif ($item->push_status === \App\Enum\AnnouncementPushStatus::Pending)
                                <x-ui.badge variant="default" class="border-0 bg-blue-500/15 text-blue-700 dark:text-blue-400">{{ $item->push_status->label() }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="outline">{{ $item->push_status->label() }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Created --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            <x-ui.local-time :value="$item->created_at" show-diff="true" />
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($canRowAct)
                                <x-admin.dropdown align="end" width="w-44">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('common.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('announcements.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.announcements.edit', $item) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('announcements.actions.edit') }}
                                        </x-admin.dropdown-item>

                                        @if ($item->push_status === \App\Enum\AnnouncementPushStatus::Sent)
                                            <x-admin.dropdown-item @click="$wire.resend({{ $item->id }})">
                                                <x-lucide-send class="size-4" />
                                                {{ __('announcements.actions.resend') }}
                                            </x-admin.dropdown-item>
                                            <x-admin.dropdown-item @click="$wire.viewStatus({{ $item->id }})">
                                                <x-lucide-info class="size-4" />
                                                {{ __('announcements.actions.view_status') }}
                                            </x-admin.dropdown-item>
                                        @elseif ($item->push_status === \App\Enum\AnnouncementPushStatus::Failed)
                                            <x-admin.dropdown-item @click="$wire.resend({{ $item->id }})">
                                                <x-lucide-rotate-ccw class="size-4" />
                                                {{ __('announcements.actions.retry') }}
                                            </x-admin.dropdown-item>
                                            <x-admin.dropdown-item @click="$wire.viewStatus({{ $item->id }})">
                                                <x-lucide-info class="size-4" />
                                                {{ __('announcements.actions.view_status') }}
                                            </x-admin.dropdown-item>
                                        @elseif ($item->push_status === \App\Enum\AnnouncementPushStatus::Pending)
                                            <x-admin.dropdown-item @click="$wire.viewStatus({{ $item->id }})">
                                                <x-lucide-loader class="size-4" />
                                                {{ __('announcements.actions.view_status') }}
                                            </x-admin.dropdown-item>
                                        @endif
                                    @endcan

                                    @can('announcements.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive"
                                            @click="$wire.confirmDelete({{ $item->id }})">
                                            <x-lucide-trash class="size-4" />
                                            {{ __('announcements.actions.delete') }}
                                        </x-admin.dropdown-item>
                                    @endcan
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-bell class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('announcements.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('announcements.filters.clear') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$announcements" />

    {{-- Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('announcements.actions.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ trans_choice('announcements.actions.selected', count($selectedIds), ['count' => count($selectedIds)]) }}</span>
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
    @include('livewire.admin.application.announcement.partials.dialogs')

</div>
