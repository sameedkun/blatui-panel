<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header title="Roles" description="Manage panel roles and the permissions they grant." :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Roles']]">
        @can('roles.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.roles.create') }}">
                    <x-lucide-plus class="size-4" />
                    Create Role
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
        search-placeholder="Search roles..." />

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
                            Role
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Permissions</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Staff</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">Created</th>
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
                                <span class="font-medium">{{ Str::headline($role->name) }}</span>
                                @if ($isProtected)
                                    <x-admin.tooltip text="Managed automatically — permissions are re-synced from config/panel.php on every seed">
                                        <x-ui.badge variant="outline" class="gap-1">
                                            <x-lucide-lock class="size-3" />
                                            Protected
                                        </x-ui.badge>
                                    </x-admin.tooltip>
                                @endif
                            </div>
                        </td>

                        {{-- Permissions count --}}
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ $role->permissions_count }} permissions</x-ui.badge>
                        </td>

                        {{-- Staff count --}}
                        <td class="px-4 py-3 text-muted-foreground">{{ $role->users_count }}</td>

                        {{-- Created --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            {{ $role->created_at?->format('M d, Y') ?? '-' }}
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($isProtected)
                                @can('roles.edit')
                                    <x-admin.tooltip text="System role — view only">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.roles.edit', $role) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">View</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan
                            @elseif ($canRowAct)
                                <x-admin.dropdown align="end" width="w-40">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('roles.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.roles.edit', $role) }}">
                                            <x-lucide-pencil class="size-4" />
                                            Edit
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('roles.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive"
                                            @click="$wire.confirmDelete({{ $role->id }})">
                                            <x-lucide-trash class="size-4" />
                                            Delete
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
                            <p class="text-sm">No roles found.</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">Clear filters</button>
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
