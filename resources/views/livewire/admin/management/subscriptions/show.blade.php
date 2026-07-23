<div class="flex flex-col gap-6">

    {{-- Header (persistent across every tab) --}}
    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()" />

    {{-- Hero banner header --}}
    @include('livewire.admin.management.subscriptions.show.components.header', ['record' => $record, 'show' => $this])

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($stats as $stat)
            <div class="relative overflow-hidden rounded-xl border border-border/80 bg-card p-4 shadow-2xs transition-all hover:border-border">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-xs font-medium text-muted-foreground">{{ $stat['label'] }}</p>
                    <div class="flex size-7 shrink-0 items-center justify-center rounded-md border border-primary/10 bg-primary/5 text-primary">
                        <x-dynamic-component :component="'lucide-' . $stat['icon']" class="size-3.5 shrink-0" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline justify-between gap-2">
                    <p class="text-xl font-bold tracking-tight text-foreground">{{ $stat['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tabs + active tab content --}}
    <x-admin.show-tabs :tabs="$this->availableTabs()" :active="$this->resolveActiveTab()">
        @if ($this->activeTabView())
            @include($this->activeTabView(), array_merge(['record' => $record, 'show' => $this, 'nextSubscription' => $nextSubscription], $this->activeTabData()))
        @endif
    </x-admin.show-tabs>

    {{-- Action dialogs --}}
    @include('livewire.admin.management.subscriptions.partials.dialogs')

</div>
