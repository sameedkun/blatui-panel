@if ($paginator->hasPages())
<x-ui.pagination>
    <x-ui.pagination-content>

        {{-- First page --}}
        <x-ui.pagination-item>
            <x-ui.pagination-link
                wire:click.prevent="gotoPage(1)"
                wire:key="first-page"
                href="#"
                aria-label="Go to first page"
                class="{{ $paginator->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}"
            >
                <x-lucide-chevrons-left class="size-4" />
            </x-ui.pagination-link>
        </x-ui.pagination-item>

        {{-- Previous --}}
        <x-ui.pagination-item>
            <x-ui.pagination-link
                wire:click.prevent="{{ $paginator->onFirstPage() ? '' : 'previousPage' }}"
                href="#"
                aria-label="Go to previous page"
                class="{{ $paginator->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}"
            >
                <x-lucide-chevron-left class="size-4" />
            </x-ui.pagination-link>
        </x-ui.pagination-item>

        {{-- Page links --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <x-ui.pagination-item>
                    <x-ui.pagination-ellipsis />
                </x-ui.pagination-item>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <x-ui.pagination-item>
                        <x-ui.pagination-link
                            wire:click.prevent="gotoPage({{ $page }})"
                            wire:key="page-{{ $page }}"
                            href="#"
                            :isActive="$page === $paginator->currentPage()"
                        >
                            {{ $page }}
                        </x-ui.pagination-link>
                    </x-ui.pagination-item>
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        <x-ui.pagination-item>
            <x-ui.pagination-link
                wire:click.prevent="{{ $paginator->hasMorePages() ? 'nextPage' : '' }}"
                href="#"
                aria-label="Go to next page"
                class="{{ ! $paginator->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}"
            >
                <x-lucide-chevron-right class="size-4" />
            </x-ui.pagination-link>
        </x-ui.pagination-item>

        {{-- Last page --}}
        <x-ui.pagination-item>
            <x-ui.pagination-link
                wire:click.prevent="gotoPage({{ $paginator->lastPage() }})"
                wire:key="last-page"
                href="#"
                aria-label="Go to last page"
                class="{{ ! $paginator->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}"
            >
                <x-lucide-chevrons-right class="size-4" />
            </x-ui.pagination-link>
        </x-ui.pagination-item>

    </x-ui.pagination-content>
</x-ui.pagination>
@endif
