<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('staff.title')" :description="__('staff.subtitle')" :breadcrumbs="[['label' => __('staff.common.home'), 'url' => route('admin.dashboard')], ['label' => __('staff.title')]]">
        @can('staff.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.staff.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('staff.actions.create') }}
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
        :search-placeholder="__('staff.filters.search')" />

    {{-- Table --}}
    @php
        $isTrashedView = ($filters['view'] ?? '') === 'trashed';
        $canBulkAct = $isTrashedView
            ? auth()->user()->canAny(['staff.restore', 'staff.force-delete'])
            : auth()->user()->canAny(['staff.ban', 'staff.unban', 'staff.delete']);
        $canRowAct = $isTrashedView
            ? auth()->user()->canAny(['staff.edit', 'staff.restore', 'staff.force-delete'])
            : auth()->user()->canAny(['staff.edit', 'staff.ban', 'staff.delete']);
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
                        <button wire:click="sort('name')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('staff.fields.staff') }}
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">{{ __('staff.fields.roles') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('staff.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left md:table-cell">
                        <button wire:click="sort('registration_date')"
                            class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('staff.fields.registered') }}
                            @if ($sortBy === 'registration_date')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground xl:table-cell">{{ __('staff.fields.last_login') }}</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($staff as $member)
                    @php
                        $isSelf = $member->id === auth()->id();
                        $isProtected = $member->isSuperAdmin() && ! auth()->user()->isSuperAdmin();
                    @endphp
                    <tr wire:key="staff-row-{{ $member->id }}"
                        class="group hover:bg-muted/30 {{ in_array((string) $member->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkAct && !$isProtected && !$isSelf)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $member->id }}')"
                                    @change="$wire.toggleSelection('{{ $member->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Staff cell: avatar + name + email + ext id --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar class="size-8 shrink-0 rounded-full">
                                    @if ($member->avatarUrl())
                                        <x-ui.avatar-image :src="$member->avatarUrl()" :alt="$member->name" />
                                    @endif
                                    <x-ui.avatar-fallback class="text-xs">
                                        {{ strtoupper(substr($member->name, 0, 2)) }}
                                    </x-ui.avatar-fallback>
                                </x-ui.avatar>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-medium">{{ $member->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-xs text-muted-foreground">{{ $member->email }}</span>
                                        {{-- External ID on hover --}}
                                        <x-admin.tooltip :text="$member->external_id" tipClass="font-mono">
                                            <span
                                                class="hidden cursor-default font-mono text-[10px] text-muted-foreground/50 group-hover:inline">
                                                {{ substr($member->external_id, 0, 8) }}
                                            </span>
                                        </x-admin.tooltip>
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Roles --}}
                        <td class="hidden px-4 py-3 lg:table-cell">
                            <div class="flex flex-wrap items-center gap-1">
                                @forelse ($member->roles as $role)
                                    @if ($role->name === config('panel.super_admin_role'))
                                        <x-ui.badge class="border-0 bg-amber-500/15 text-amber-700 dark:text-amber-400">
                                            <x-lucide-crown class="size-3" />
                                            {{ $roleLabels[$role->name] ?? Str::headline($role->name) }}
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary">{{ $roleLabels[$role->name] ?? Str::headline($role->name) }}</x-ui.badge>
                                    @endif
                                @empty
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($member->banned_at)
                                <x-ui.badge variant="destructive">{{ __('staff.status.banned') }}</x-ui.badge>
                            @elseif(!$member->email_verified_at)
                                <x-ui.badge variant="secondary">{{ __('staff.status.unverified') }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="default"
                                    class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-0">{{ __('staff.status.active') }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Registered --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            <x-ui.local-time :value="$member->registration_date" :format="__('staff.index.date_format')" />
                        </td>

                        {{-- Last login --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                            @if ($member->last_login)
                                <x-ui.local-time :value="$member->last_login" show-diff="true" />
                            @else
                                {{ __('staff.common.never') }}
                            @endif
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($canRowAct && $isProtected)
                                <x-admin.tooltip :text="__('staff.index.protected_tooltip')">
                                    <x-ui.badge variant="outline" class="gap-1">
                                        <x-lucide-lock class="size-3" />
                                        {{ __('staff.status.protected') }}
                                    </x-ui.badge>
                                </x-admin.tooltip>
                            @elseif ($canRowAct)
                                <x-admin.dropdown align="end" width="w-44">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('staff.common.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('staff.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.staff.edit', $member) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('staff.actions.edit') }}
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @unless ($isSelf)
                                        @if (!$member->trashed())
                                            @can('staff.ban')
                                                @if (!$member->banned_at)
                                                    <x-admin.dropdown-item @click="$wire.openBanDialog({{ $member->id }})">
                                                        <x-lucide-ban class="size-4" />
                                                        {{ __('staff.actions.ban') }}
                                                    </x-admin.dropdown-item>
                                                @else
                                                    <x-admin.dropdown-item @click="$wire.unban({{ $member->id }})">
                                                        <x-lucide-shield-check class="size-4" />
                                                        {{ __('staff.actions.unban') }}
                                                    </x-admin.dropdown-item>
                                                @endif
                                            @endcan

                                            @can('staff.delete')
                                                <x-admin.dropdown-separator />
                                                <x-admin.dropdown-item variant="destructive"
                                                    @click="$wire.confirmDelete({{ $member->id }})">
                                                    <x-lucide-trash class="size-4" />
                                                    {{ __('staff.actions.delete') }}
                                                </x-admin.dropdown-item>
                                            @endcan
                                        @else
                                            @can('staff.restore')
                                                <x-admin.dropdown-item @click="$wire.confirmRestore({{ $member->id }})">
                                                    <x-lucide-rotate-ccw class="size-4" />
                                                    {{ __('staff.actions.restore') }}
                                                </x-admin.dropdown-item>
                                            @endcan

                                            @can('staff.force-delete')
                                                <x-admin.dropdown-separator />
                                                <x-admin.dropdown-item variant="destructive"
                                                    @click="$wire.confirmForceDelete({{ $member->id }})">
                                                    <x-lucide-trash-2 class="size-4" />
                                                    {{ __('staff.actions.force_delete') }}
                                                </x-admin.dropdown-item>
                                            @endcan
                                        @endif
                                    @endunless
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-shield-user class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('staff.index.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('staff.common.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$staff" />

    {{--  Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('staff.common.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ __('staff.common.selected', ['count' => count($selectedIds)]) }}</span>
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

    {{-- ── Confirmation dialogs ──────────────────────────────────────────── --}}
    @include('livewire.admin.administration.staff.partials.dialogs')

</div>
