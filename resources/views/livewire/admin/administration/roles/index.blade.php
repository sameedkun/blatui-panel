<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('roles.title')" :description="__('roles.subtitle')" :breadcrumbs="[['label' => __('roles.common.home'), 'url' => route('admin.dashboard')], ['label' => __('roles.title')]]">
        @can('roles.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.roles.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('roles.actions.create') }}
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
        :search-placeholder="__('roles.index.search')" />

    {{-- Table --}}
    @php
        $canRowAct = auth()->user()->canAny(['roles.edit', 'roles.delete']);
    @endphp
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('name')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('roles.fields.role') }}
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('roles.fields.permissions') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('roles.fields.staff') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('roles.fields.created') }}</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($roles as $role)
                    @php
                        $isProtected = in_array($role->name, config('panel.protected_roles', []), true);
                        $isSuperAdminRole = $role->name === config('panel.super_admin_role');
                    @endphp
                    <tr wire:key="role-row-{{ $role->id }}" class="hover:bg-muted/30">

                        {{-- Role name --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($isSuperAdminRole)
                                    <x-lucide-crown class="size-4 shrink-0 text-amber-500" />
                                @endif
                                <span class="font-medium">{{ $roleLabels[$role->name] }}</span>
                                @if ($isProtected)
                                    <x-admin.tooltip :text="__('roles.index.protected_tooltip')">
                                        <x-ui.badge variant="outline" class="gap-1">
                                            <x-lucide-lock class="size-3" />
                                            {{ __('roles.status.protected') }}
                                        </x-ui.badge>
                                    </x-admin.tooltip>
                                @endif
                            </div>
                        </td>

                        {{-- Permissions count --}}
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ trans_choice('roles.index.permission_count', $role->permissions_count, ['count' => $role->permissions_count]) }}</x-ui.badge>
                        </td>

                        {{-- Staff count --}}
                        <td class="px-4 py-3 text-muted-foreground">{{ $role->users_count }}</td>

                        {{-- Created --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            <x-ui.local-time :value="$role->created_at" :format="__('roles.index.date_format')" />
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($isProtected)
                                @can('roles.edit')
                                    <x-admin.tooltip :text="__('roles.index.view_only_tooltip')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.roles.edit', $role) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">{{ __('roles.common.view') }}</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan
                            @elseif ($canRowAct)
                                <x-admin.dropdown align="end" width="w-40">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('roles.common.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('roles.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.roles.edit', $role) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('roles.actions.edit') }}
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('roles.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive"
                                            @click="$wire.confirmDelete({{ $role->id }})">
                                            <x-lucide-trash class="size-4" />
                                            {{ __('roles.actions.delete') }}
                                        </x-admin.dropdown-item>
                                    @endcan
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-key class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('roles.index.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('roles.common.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$roles" />

    {{-- ── Confirmation dialogs ──────────────────────────────────────────── --}}
    @include('livewire.admin.administration.roles.partials.dialogs')

</div>
