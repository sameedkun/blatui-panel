{{--
    Renders one App\Support\Dashboard\Blocks\Row: a responsive grid whose
    widest breakpoint has `columns` tracks, each block spanning `span` of them.
    Every block draws itself through its own partial ($block->view()), so this
    renderer never needs to know what a block is.

    Class strings are literal (not interpolated) so Tailwind's scanner sees them.
--}}
@props(['row'])

@php
    /** @var \App\Support\Dashboard\Blocks\Row $row */
    $grids = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 lg:grid-cols-2',
        3 => 'grid-cols-1 lg:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4',
    ];
    $spans = [
        1 => '',
        2 => 'lg:col-span-2',
        3 => 'lg:col-span-3',
        4 => 'sm:col-span-2 xl:col-span-4',
    ];
@endphp

<div class="grid gap-4 {{ $grids[$row->columns] }}">
    @foreach ($row->blocks as $block)
        <div class="min-w-0 {{ $spans[min($block->span, $row->columns)] }}">
            @include($block->view(), ['block' => $block])
        </div>
    @endforeach
</div>
