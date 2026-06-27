@props([
    'title',
    'description' => null,
    'breadcrumbs' => [],
    'back' => null,
])

<x-ui.page-header
    :title="$title"
    :description="$description"
    separator
>
    @if(count($breadcrumbs))
        <x-slot:breadcrumb>
            <x-ui.breadcrumb>
                <x-ui.breadcrumb-list>

                    @foreach($breadcrumbs as $breadcrumb)
                        <x-ui.breadcrumb-item>
                            @if(isset($breadcrumb['url']))
                                <x-ui.breadcrumb-link :href="$breadcrumb['url']">
                                    {{ $breadcrumb['label'] }}
                                </x-ui.breadcrumb-link>
                            @else
                                <x-ui.breadcrumb-page>
                                    {{ $breadcrumb['label'] }}
                                </x-ui.breadcrumb-page>
                            @endif
                        </x-ui.breadcrumb-item>

                        @if(! $loop->last)
                            <x-ui.breadcrumb-separator />
                        @endif
                    @endforeach

                </x-ui.breadcrumb-list>
            </x-ui.breadcrumb>
        </x-slot:breadcrumb>
    @endif

    @if($back || isset($actions))
        <x-slot:actions>
            @if($back)
                <x-ui.button variant="ghost" :href="$back">
                    <x-lucide-arrow-left class="size-4" />
                    Back
                </x-ui.button>
            @endif

            {{ $actions ?? '' }}
        </x-slot:actions>
    @endif

</x-ui.page-header>