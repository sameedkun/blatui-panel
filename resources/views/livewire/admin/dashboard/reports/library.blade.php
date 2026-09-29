{{-- Every report the viewer may generate, with what it contains. --}}
@php
    $canCreate = auth()->user()->can('dashboard.reports.create');
    $canManage = auth()->user()->can('dashboard.reports.manage');
@endphp

@if (count($definitions) === 0)
    <x-ui.empty class="border">
        <x-ui.empty-header>
            <x-ui.empty-media variant="icon"><x-lucide-library /></x-ui.empty-media>
            <x-ui.empty-title>{{ __('dashboard.reports.empty.library_title') }}</x-ui.empty-title>
            <x-ui.empty-description>{{ __('dashboard.reports.empty.library_body') }}</x-ui.empty-description>
        </x-ui.empty-header>
    </x-ui.empty>
@else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($definitions as $key => $definition)
            <x-ui.card wire:key="definition-{{ $key }}" class="flex flex-col gap-4 p-5">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <x-dynamic-component :component="'lucide-'.$definition->icon()" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $definition->label() }}</p>
                        <p class="mt-0.5 text-sm text-muted-foreground">{{ $definition->description() }}</p>
                    </div>
                </div>

                <div class="flex flex-1 flex-col gap-2 text-xs">
                    <p class="font-medium text-muted-foreground">{{ __('dashboard.reports.library.columns') }}</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($definition->columns() as $heading)
                            <x-ui.badge variant="secondary" size="sm">{{ $heading }}</x-ui.badge>
                        @endforeach
                    </div>
                    @if (count($definition->filters()))
                        <p class="mt-1 font-medium text-muted-foreground">{{ __('dashboard.reports.library.filters') }}</p>
                        <p class="text-muted-foreground">{{ collect($definition->filters())->pluck('label')->implode(' · ') }}</p>
                    @endif
                </div>

                @if ($canCreate || $canManage)
                    <div class="flex items-center gap-2 border-t pt-4">
                        @if ($canCreate)
                            <x-ui.button size="sm" wire:click="openGenerate('{{ $key }}')">
                                <x-lucide-play class="size-3.5" />
                                {{ __('dashboard.reports.actions.generate') }}
                            </x-ui.button>
                        @endif
                        @if ($canManage)
                            <x-ui.button size="sm" variant="outline" wire:click="openSchedule(null, '{{ $key }}')">
                                <x-lucide-calendar-plus class="size-3.5" />
                                {{ __('dashboard.reports.actions.schedule') }}
                            </x-ui.button>
                        @endif
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
@endif
