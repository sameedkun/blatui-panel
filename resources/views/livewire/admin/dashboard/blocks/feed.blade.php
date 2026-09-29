@php
    /** @var \App\Support\Dashboard\Blocks\Feed $block */
    $tones = [
        'success' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
        'info' => 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
        'warning' => 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        'danger' => 'bg-red-500/15 text-red-700 dark:text-red-400',
    ];
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    @if ($block->items === [])
        <div class="flex flex-col items-center gap-2 py-10 text-center text-muted-foreground">
            <x-lucide-inbox class="size-7 opacity-40" />
            <p class="text-xs">{{ $block->emptyMessage ?? __('dashboard.no_data') }}</p>
        </div>
    @else
        <ul class="-my-1 flex flex-col">
            @foreach ($block->items as $item)
                <li class="relative flex gap-3 py-2.5">
                    @if (! $loop->last)
                        <span class="absolute top-10 bottom-0 left-4 w-px bg-border" aria-hidden="true"></span>
                    @endif
                    <span class="relative flex size-8 shrink-0 items-center justify-center rounded-full {{ $tones[$item['tone']] ?? 'bg-muted text-muted-foreground' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-3.5" />
                    </span>
                    <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                        <div class="min-w-0">
                            @if ($item['href'])
                                <a href="{{ $item['href'] }}" wire:navigate class="truncate text-sm font-medium text-foreground hover:underline">{{ $item['title'] }}</a>
                            @else
                                <p class="truncate text-sm font-medium text-foreground">{{ $item['title'] }}</p>
                            @endif
                            @if ($item['description'])
                                <p class="truncate text-xs text-muted-foreground">{{ $item['description'] }}</p>
                            @endif
                        </div>
                        @if ($item['time'])
                            <x-ui.local-time :value="$item['time']" format="smart" class="shrink-0 text-xs whitespace-nowrap text-muted-foreground" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.dashboard.card>
