{{-- Skeleton shown while a lazy tab fetches its data. Root element must be a div to
     match the tab components' own root. --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach (range(1, 4) as $i)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.skeleton class="h-4 w-40" />
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                <x-ui.skeleton class="h-[220px] w-full" />
            </x-ui.card-content>
        </x-ui.card>
    @endforeach
</div>
