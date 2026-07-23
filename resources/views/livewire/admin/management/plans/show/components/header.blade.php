@php
    /** @var \App\Models\Plan $record */
@endphp

<div class="relative overflow-hidden rounded-xl border border-border bg-card p-6 shadow-sm">
    {{-- Ambient backdrop glow --}}
    <div class="pointer-events-none absolute -right-12 -top-12 size-56 rounded-full bg-primary/5 blur-3xl"></div>

    <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">

        {{-- Identity --}}
        <div class="flex items-start gap-4 sm:items-center">
            <div class="flex size-14 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-br from-primary/15 via-primary/10 to-primary/5 text-primary shadow-xs">
                <x-lucide-package class="size-7" />
            </div>

            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="truncate text-xl font-bold text-foreground tracking-tight">{{ $record->name }}</h1>
                    @if ($record->is_best_deal)
                        <x-ui.badge variant="default" class="gap-1 border-0 bg-amber-500/15 text-amber-700 dark:text-amber-400 font-medium">
                            <x-lucide-star class="size-3.5 fill-current" />
                            Best Deal
                        </x-ui.badge>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-mono text-muted-foreground bg-muted/60 px-2 py-0.5 rounded border border-border/50">/{{ $record->slug }}</span>
                    <span class="text-muted-foreground">•</span>
                    @if ($record->is_active)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground border border-border">
                            Inactive
                        </span>
                    @endif
                    <x-ui.badge variant="outline" class="text-xs">Sort: {{ $record->sort_order }}</x-ui.badge>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex shrink-0 items-center gap-2.5 pt-2 sm:pt-0">
            @can('plans.edit')
                <x-ui.button variant="outline" href="{{ route('admin.plans.edit', $record) }}" class="gap-1.5 shadow-2xs">
                    <x-lucide-pencil class="size-4" />
                    <span>Edit Plan</span>
                </x-ui.button>

                <x-ui.button variant="{{ $record->is_active ? 'secondary' : 'default' }}"
                    @click="$wire.toggleActive({{ $record->id }})" class="gap-1.5 shadow-2xs">
                    @if ($record->is_active)
                        <x-lucide-circle-slash class="size-4" />
                        <span>Deactivate</span>
                    @else
                        <x-lucide-check-circle class="size-4" />
                        <span>Activate</span>
                    @endif
                </x-ui.button>
            @endcan

            <x-admin.dropdown align="end" width="w-52">
                <x-slot:trigger>
                    <x-ui.button variant="outline" size="icon" class="size-9 shadow-2xs">
                        <x-lucide-more-vertical class="size-4" />
                    </x-ui.button>
                </x-slot:trigger>

                @can('plans.delete')
                    <x-admin.dropdown-item variant="destructive" @click="$wire.confirmDelete({{ $record->id }})">
                        <x-lucide-trash class="size-4" />
                        <span>Delete Plan</span>
                    </x-admin.dropdown-item>
                @endcan
            </x-admin.dropdown>
        </div>

    </div>
</div>
