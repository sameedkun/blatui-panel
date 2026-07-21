@props([
    'value' => null,
    'format' => 'default', // 'default' | 'Y-m-d H:i:s' | 'MMM D, YYYY' | 'smart'
    'showDiff' => false,
])

@if ($value)
    @php
        $date = $value instanceof \Carbon\CarbonInterface ? $value : \Carbon\Carbon::parse($value);
        $isoString = $date->toIso8601String();
        
        $fallbackFormat = match ($format) {
            'Y-m-d H:i:s' => 'Y-m-d H:i:s',
            'MMM D, YYYY' => 'M d, Y',
            default => 'M j, Y g:i A',
        };
    @endphp
    <time 
        datetime="{{ $isoString }}"
        data-format="{{ $format }}"
        @if ($showDiff) data-diff="true" @endif
        x-data="localTime"
        x-text="formatted"
        {{ $attributes->twMerge('inline-block') }}
    >
        {{ $date->format($fallbackFormat) }} UTC
    </time>
@endif
