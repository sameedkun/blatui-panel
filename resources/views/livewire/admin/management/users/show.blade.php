<div class="flex flex-col gap-6">

    {{-- Header (persistent across every tab) --}}
    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()" />

    @include('livewire.admin.management.users.profile.components.header', ['record' => $record])

    {{-- Statistics row --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($stats as $stat)
            <x-ui.card class="gap-1">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-muted-foreground">{{ $stat['label'] }}</p>
                    <x-dynamic-component :component="'lucide-' . $stat['icon']" class="size-4 shrink-0 text-muted-foreground" />
                </div>
                @if (is_null($stat['value']))
                    <p class="mt-1 text-sm text-muted-foreground/60">Coming soon</p>
                @else
                    <p class="mt-1 text-lg font-semibold">{{ $stat['value'] }}</p>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    {{-- Tabs + active tab content — rendered purely from the registry --}}
    <x-admin.show-tabs :tabs="$this->availableTabs()" :active="$this->resolveActiveTab()">
        @if ($this->activeTabView())
            @include($this->activeTabView(), array_merge(['record' => $record, 'show' => $this], $this->activeTabData()))
        @endif
    </x-admin.show-tabs>

    {{-- Shared single-row action dialogs (same components as the Users index) --}}
    @include('livewire.admin.management.users.partials.single-row-dialogs')

    {{-- Subscription-management dialogs (profile-only) --}}
    @include('livewire.admin.management.users.partials.subscription-dialogs')

    {{-- Device-management dialogs (profile-only) --}}
    @include('livewire.admin.management.users.partials.device-dialogs')

</div>
