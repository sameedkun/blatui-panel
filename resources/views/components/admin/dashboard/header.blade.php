{{--
    The header every Dashboard page shares — Overview, Analytics and Reports —
    so the three sub-pages read as one area: breadcrumb, title + description on
    the left, page controls (range picker, refresh, primary action) on the right.

    Props:
      description  supporting line under the title
      breadcrumbs  [['label' => ..., 'url' => ...?], ...]
    Slots:
      default      the title (may contain markup, e.g. the Overview's live greeting)
      actions      right-aligned controls
--}}
@props([
    'description' => null,
    'breadcrumbs' => [],
])

<x-ui.page-header :description="$description" separator>
    @if (count($breadcrumbs))
        <x-slot:breadcrumb>
            <x-ui.breadcrumb>
                <x-ui.breadcrumb-list>
                    @foreach ($breadcrumbs as $breadcrumb)
                        <x-ui.breadcrumb-item>
                            @if (isset($breadcrumb['url']))
                                <x-ui.breadcrumb-link :href="$breadcrumb['url']" wire:navigate>{{ $breadcrumb['label'] }}</x-ui.breadcrumb-link>
                            @else
                                <x-ui.breadcrumb-page>{{ $breadcrumb['label'] }}</x-ui.breadcrumb-page>
                            @endif
                        </x-ui.breadcrumb-item>
                        @if (! $loop->last)
                            <x-ui.breadcrumb-separator />
                        @endif
                    @endforeach
                </x-ui.breadcrumb-list>
            </x-ui.breadcrumb>
        </x-slot:breadcrumb>
    @endif

    {{ $slot }}

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-ui.page-header>
