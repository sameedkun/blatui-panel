{{--
    Copies a value to the clipboard and briefly confirms it.

    Props:
      value  string — what gets copied
      label  string — optional visible text; icon-only when omitted
--}}
@props(['value', 'label' => null])

<button type="button"
    x-data="{ copied: false }"
    @click.stop="navigator.clipboard.writeText(@js($value)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
    title="{{ __('api_logs.actions.copy') }}"
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 rounded p-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground']) }}>
    <x-lucide-copy class="size-3.5" x-show="! copied" />
    <x-lucide-check class="size-3.5 text-emerald-600" x-show="copied" x-cloak />
    @if ($label)
        <span x-text="copied ? @js(__('api_logs.actions.copied')) : @js($label)">{{ $label }}</span>
    @else
        <span class="sr-only">{{ __('api_logs.actions.copy') }}</span>
    @endif
</button>
