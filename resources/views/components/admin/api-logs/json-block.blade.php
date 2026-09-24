{{--
    A stored (already sanitized) body/query value. Arrays render as pretty JSON;
    a string is a body truncated at storage time and renders as the raw text.

    Props:
      value      mixed — the stored value (array, truncated string, or null)
      truncated  bool  — whether storage cut it off
--}}
@props(['value' => null, 'truncated' => false])

@if ($value === null || $value === [])
    <p class="text-xs text-muted-foreground">{{ __('api_logs.show.empty_body') }}</p>
@else
    @if ($truncated)
        <p class="mb-1.5 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
            <x-lucide-scissors class="size-3.5" />
            {{ __('api_logs.show.truncated', ['kb' => config('api_logs.max_body_kb', 32)]) }}
        </p>
    @endif
    @php
        $text = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    <div class="relative">
        <x-admin.copy-button :value="$text" class="absolute right-2 top-2 bg-background/80" />
        <pre {{ $attributes->merge(['class' => 'max-h-[28rem] overflow-auto whitespace-pre-wrap break-all rounded-md border border-border bg-muted/20 p-3 pr-10 font-mono text-xs']) }}>{{ $text }}</pre>
    </div>
@endif
