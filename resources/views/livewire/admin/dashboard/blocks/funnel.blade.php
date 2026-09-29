@php
    /** @var \App\Support\Dashboard\Blocks\Funnel $block */
    $accents = [
        'success' => 'bg-emerald-500',
        'info' => 'bg-sky-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
    ];
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    <ol class="flex flex-col gap-3 md:flex-row md:items-stretch">
        @foreach ($block->steps as $index => $step)
            @if ($index > 0)
                <li aria-hidden="true" class="flex items-center justify-center text-muted-foreground">
                    <x-lucide-chevron-right class="hidden size-4 md:block" />
                    <x-lucide-chevron-down class="size-4 md:hidden" />
                </li>
            @endif

            <li class="relative flex flex-1 flex-col gap-1 overflow-hidden rounded-lg border border-border bg-muted/20 p-4 pl-5">
                <span class="absolute inset-y-0 left-0 w-1 {{ $accents[$step['tone']] ?? 'bg-primary' }}"></span>
                <span class="text-xs font-medium text-muted-foreground">{{ $step['label'] }}</span>
                <span class="text-2xl font-semibold tracking-tight tabular-nums text-foreground">{{ number_format($step['value']) }}</span>
                @if (($conversion = $block->conversionAt($index)) !== null)
                    <span class="text-xs font-medium text-foreground">{{ __('dashboard.funnels.of_previous', ['percent' => $conversion]) }}</span>
                @endif
                @if ($step['hint'])
                    <span class="text-xs text-muted-foreground/80">{{ $step['hint'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</x-admin.dashboard.card>
