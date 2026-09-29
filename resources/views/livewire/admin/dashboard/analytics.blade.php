@php
    /** @var array<string, \App\Support\Dashboard\Analytics\AnalyticsSection> $sections */
    /** @var \App\Support\Dashboard\Analytics\AnalyticsSection|null $active */
    $tabs = collect($sections)->map(fn ($section): array => ['label' => $section->label(), 'icon' => $section->icon()])->all();
@endphp

<div class="flex flex-col gap-6">

    <x-admin.dashboard.header :description="$active?->description() ?? __('dashboard.analytics.subtitle')"
        :breadcrumbs="[
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('dashboard.analytics.title'), 'url' => route('admin.dashboard.analytics')],
            ...($active ? [['label' => $active->label()]] : []),
        ]">
        {{ $active?->label() ?? __('dashboard.analytics.title') }}

        <x-slot:actions>
            @include('livewire.admin.dashboard.partials.range-controls')
        </x-slot:actions>
    </x-admin.dashboard.header>

    @if ($active === null)
        <x-ui.empty class="border">
            <x-ui.empty-header>
                <x-ui.empty-media variant="icon"><x-lucide-chart-line /></x-ui.empty-media>
                <x-ui.empty-title>{{ __('dashboard.analytics.empty_title') }}</x-ui.empty-title>
                <x-ui.empty-description>{{ __('dashboard.analytics.empty_description') }}</x-ui.empty-description>
            </x-ui.empty-header>
        </x-ui.empty>
    @else
        <x-admin.show-tabs :tabs="$tabs" :active="$active->key()">
            <div class="flex flex-col gap-4 transition-opacity" wire:loading.class="pointer-events-none opacity-60" wire:target="selectRange,selectTab,refresh">
                @if (! $active->supports($selectedRange))
                    <p class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">{{ __('dashboard.analytics.range_unsupported') }}</p>
                @endif

                @foreach ($rows as $row)
                    <div wire:key="analytics-{{ $active->key() }}-row-{{ $loop->index }}">
                        <x-admin.dashboard.row :row="$row" />
                    </div>
                @endforeach
            </div>
        </x-admin.show-tabs>
    @endif
</div>
