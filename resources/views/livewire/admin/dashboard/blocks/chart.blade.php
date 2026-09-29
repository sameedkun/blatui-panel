{{--
    Chart block. The wrapper's wire:key hashes the block's data so Alpine
    re-initialises the chart whenever a range change alters the series.
    x-ui.chart ships `aspect-video`; `aspect-auto` + an explicit height
    overrides it (.ai/rules/views.md).
--}}
@php
    /** @var \App\Support\Dashboard\Blocks\Chart $block */
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    @if ($block->summary !== null)
        <x-slot:aside>
            <span class="text-lg font-semibold tabular-nums text-foreground">{{ $block->summary }}</span>
        </x-slot:aside>
    @endif

    @if ($block->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 text-center text-muted-foreground" style="height: {{ $block->height }}px">
            <x-lucide-chart-no-axes-column class="size-8 opacity-40" />
            <p class="text-xs">{{ __('dashboard.no_data') }}</p>
        </div>
    @else
        <div wire:key="chart-{{ $block->fingerprint() }}">
            <x-ui.chart
                :type="$block->type"
                :height="$block->height"
                :label="$block->title"
                :series="$block->series"
                :labels="$block->type === \App\Support\Dashboard\Blocks\Chart::DONUT ? $block->labels : []"
                :colors="$block->colors"
                :options="$block->options()"
                class="aspect-auto" />
        </div>
    @endif
</x-admin.dashboard.card>
