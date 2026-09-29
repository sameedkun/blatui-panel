@php
    /** @var \App\Support\Dashboard\Blocks\ActionList $block */
    $actions = array_filter($block->actions, fn (array $action): bool => ! $action['permission'] || auth()->user()?->can($action['permission']));
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon">
    @if ($actions === [])
        <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.actions.none') }}</p>
    @else
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1">
            @foreach ($actions as $action)
                <a href="{{ $action['href'] }}" wire:navigate
                    class="group flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors hover:border-foreground/20 hover:bg-muted/40">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <x-dynamic-component :component="'lucide-'.$action['icon']" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-foreground">{{ $action['label'] }}</span>
                        @if ($action['description'])
                            <span class="block truncate text-xs text-muted-foreground">{{ $action['description'] }}</span>
                        @endif
                    </span>
                    <x-lucide-arrow-right class="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </a>
            @endforeach
        </div>
    @endif
</x-admin.dashboard.card>
