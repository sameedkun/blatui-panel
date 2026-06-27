@props(['label', 'value', 'icon', 'description' => ''])

<x-ui.card>
    <div class="flex items-start justify-between">
        <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
        <x-dynamic-component :component="'lucide-' . $icon" class="size-5 shrink-0 text-muted-foreground" />
    </div>
    <div class="mt-3">
        <div class="text-2xl font-bold">{{ is_numeric($value) ? number_format($value) : $value }}</div>
        @if ($description)
            <p class="mt-0.5 text-xs text-muted-foreground">{{ $description }}</p>
        @endif
    </div>
</x-ui.card>
