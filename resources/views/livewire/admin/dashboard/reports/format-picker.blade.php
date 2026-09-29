{{-- Segmented CSV / XLSX / PDF picker bound to the Livewire property named by $model. --}}
<div class="grid grid-cols-3 gap-2" role="radiogroup">
    @foreach ($formats as $option)
        <button type="button" role="radio" aria-checked="{{ $selected === $option->value ? 'true' : 'false' }}"
            wire:click="$set('{{ $model }}', '{{ $option->value }}')"
            @class([
                'flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                'border-primary bg-primary/5 text-foreground ring-1 ring-primary' => $selected === $option->value,
                'border-border text-muted-foreground hover:text-foreground' => $selected !== $option->value,
            ])>
            <x-dynamic-component :component="'lucide-'.$option->icon()" class="size-4" />
            {{ $option->label() }}
        </button>
    @endforeach
</div>
