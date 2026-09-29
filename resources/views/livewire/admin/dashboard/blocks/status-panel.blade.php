@php
    /** @var \App\Support\Dashboard\Blocks\StatusPanel $block */
    $total = $block->segmentTotal();
    $fills = [
        'success' => 'bg-emerald-500',
        'info' => 'bg-sky-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
    ];
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    <div class="flex flex-col gap-5">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($block->figures as $figure)
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-muted-foreground">{{ $figure['label'] }}</dt>
                    <dd class="text-xl font-semibold tracking-tight tabular-nums text-foreground">
                        {{ \App\Support\Dashboard\Format::value($figure['value'], $figure['format']) }}
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($block->segments !== [])
            <div class="flex flex-col gap-2.5">
                @if ($block->segmentsTitle)
                    <p class="text-xs font-medium text-muted-foreground">{{ $block->segmentsTitle }}</p>
                @endif

                <div class="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                    @if ($total > 0)
                        @foreach ($block->segments as $segment)
                            @if ($segment['value'] > 0)
                                <div class="h-full {{ $fills[$segment['tone']] ?? 'bg-muted-foreground/40' }}"
                                    style="width: {{ round($segment['value'] / $total * 100, 2) }}%"
                                    title="{{ $segment['label'] }}: {{ number_format($segment['value']) }}"></div>
                            @endif
                        @endforeach
                    @endif
                </div>

                <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs">
                    @foreach ($block->segments as $segment)
                        <li class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full {{ $fills[$segment['tone']] ?? 'bg-muted-foreground/40' }}"></span>
                            <span class="text-muted-foreground">{{ $segment['label'] }}</span>
                            <span class="font-medium tabular-nums text-foreground">{{ number_format($segment['value']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-admin.dashboard.card>
