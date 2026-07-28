{{--
    Reusable admin pagination bar.

    Wraps the per-page selector, page counter, and BlatUI pagination links into
    one responsive component. Usage:
        <x-admin.pagination :paginator="$users" per-page-model="perPage" />

    Props:
      paginator      LengthAwarePaginator instance
      perPageModel   Livewire property name bound to the per-page select (default "perPage")
      linksView      custom pagination links view (default "livewire.admin.partials.pagination")
--}}
@props([
    'paginator',
    'perPageModel' => 'perPage',
    'linksView' => 'livewire.admin.partials.pagination',
])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

    {{-- Per-page selector --}}
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <x-ui.select native wire:model.live="{{ $perPageModel }}" class="h-8 w-[66px] text-sm">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </x-ui.select>
        <span>{{ __('pagination.rows_per_page') }}</span>
    </div>

    {{-- Page counter + navigation --}}
    <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
        <span class="whitespace-nowrap text-sm text-muted-foreground">
            {{ __('pagination.page_x_of_y', ['current' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}
        </span>
        {{ $paginator->links($linksView) }}
    </div>

</div>
