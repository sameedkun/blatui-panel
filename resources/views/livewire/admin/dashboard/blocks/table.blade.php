@php
    /** @var \App\Support\Dashboard\Blocks\Table $block */
@endphp

<x-admin.dashboard.card :title="$block->title" :description="$block->description" :icon="$block->icon"
    :href="$block->href" :href-permission="$block->hrefPermission">
    @if ($block->rows === [])
        <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
    @else
        <div class="-mx-5 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-y border-border bg-muted/40 text-xs text-muted-foreground">
                        @foreach ($block->columns as $column)
                            <th @class([
                                'px-5 py-2 font-medium whitespace-nowrap',
                                'text-left' => $column['align'] === 'left',
                                'text-right' => $column['align'] === 'right',
                            ])>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($block->rows as $row)
                        <tr class="hover:bg-muted/30">
                            @foreach ($block->columns as $key => $column)
                                <td @class([
                                    'px-5 py-2.5 whitespace-nowrap',
                                    'text-left' => $column['align'] === 'left',
                                    'text-right tabular-nums' => $column['align'] === 'right',
                                    'font-medium text-foreground' => $loop->first,
                                    'text-muted-foreground' => ! $loop->first,
                                ])>{{ $block->cell($row, $key) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin.dashboard.card>
