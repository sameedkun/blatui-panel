{{--
    Ranked horizontal bars for a label => count breakdown.

    Expects:
      $items  array<string, int>|null  — null means "not available for this range"
      $title  string
      $icon   string   lucide icon name
      $note   ?string  shown when $items is null
      $links  array<string, string>  optional label => url map
      $mono   bool     render labels in a monospace font
--}}
@php
    $links ??= [];
    $mono ??= false;
    $max = $items ? max($items) : 0;
@endphp

<x-ui.card>
    <x-ui.card-header class="pb-3">
        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
            <x-dynamic-component :component="'lucide-'.$icon" class="size-4 text-muted-foreground" />
            {{ $title }}
        </x-ui.card-title>
    </x-ui.card-header>
    <x-ui.card-content>
        @if ($items === null)
            <p class="py-6 text-center text-xs text-muted-foreground">{{ $note ?? __('api_logs.analytics.no_data') }}</p>
        @elseif ($items === [])
            <p class="py-6 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
        @else
            <ul class="flex flex-col gap-1.5">
                @foreach ($items as $label => $count)
                    @php $href = $links[$label] ?? null; @endphp
                    <li wire:key="bar-{{ md5($title.$label) }}" class="relative overflow-hidden rounded">
                        <span class="absolute inset-y-0 left-0 rounded bg-primary/10" style="width: {{ $max > 0 ? max(2, $count / $max * 100) : 0 }}%"></span>
                        <span class="relative flex items-center justify-between gap-3 px-2 py-1 text-xs">
                            @if ($href)
                                <a href="{{ $href }}" wire:navigate @class(['truncate hover:underline', 'font-mono' => $mono])>{{ $label }}</a>
                            @else
                                <span @class(['truncate', 'font-mono' => $mono])>{{ $label }}</span>
                            @endif
                            <span class="font-mono tabular-nums text-muted-foreground">{{ number_format($count) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card-content>
</x-ui.card>
