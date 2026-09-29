@php
    /** @var \App\Support\Dashboard\Blocks\KeyFigures $block */
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    <dl class="grid grid-cols-[repeat(auto-fit,minmax(9.5rem,1fr))] gap-px overflow-hidden rounded-lg border border-border bg-border">
        @foreach ($block->figures as $figure)
            <div class="flex flex-col gap-1 bg-card p-4">
                <dt class="text-xs font-medium text-muted-foreground">{{ $figure['label'] }}</dt>
                <dd class="text-xl font-semibold tracking-tight tabular-nums text-foreground">
                    {{ \App\Support\Dashboard\Format::value($figure['value'], $figure['format']) }}
                </dd>
                @if ($figure['hint'])
                    <dd class="text-xs text-muted-foreground/80">{{ $figure['hint'] }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
</x-admin.dashboard.card>
