@php
    /** @var \App\Support\Dashboard\Blocks\BarList $block */
    $max = $block->max();
    $total = $block->total();
    $bars = [
        'success' => 'bg-emerald-500/80',
        'info' => 'bg-sky-500/80',
        'warning' => 'bg-amber-500/80',
        'danger' => 'bg-rose-500/80',
    ];
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    @if ($block->isEmpty())
        <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($block->items as $item)
                <li class="flex flex-col gap-1.5">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate text-foreground" title="{{ $item['label'] }}">
                            {{ $item['label'] }}
                            @if ($item['hint'])
                                <span class="text-xs text-muted-foreground">· {{ $item['hint'] }}</span>
                            @endif
                        </span>
                        <span class="flex shrink-0 items-baseline gap-2 tabular-nums">
                            <span class="font-medium text-foreground">{{ \App\Support\Dashboard\Format::value($item['value'], $block->format) }}</span>
                            @if ($block->showShare)
                                <span class="w-12 text-right text-xs text-muted-foreground">{{ \App\Support\Dashboard\Format::value(\App\Support\Dashboard\Format::share($item['value'], $total), \App\Support\Dashboard\Format::PERCENT) }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full {{ $bars[$item['tone']] ?? 'bg-primary/80' }}"
                            style="width: {{ $max > 0 ? max(1, round($item['value'] / $max * 100, 1)) : 0 }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.dashboard.card>
