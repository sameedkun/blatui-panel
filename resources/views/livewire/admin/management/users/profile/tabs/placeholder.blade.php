{{--
    Generic "coming soon" tab renderer. Registered tabs whose module isn't built
    yet point their `view` here and pass label/icon/description via the tab's
    `data` closure. When the real module ships, only that tab's `view` changes —
    the registry and shell stay put. No fake data, no empty tables.

    Expects: $label, $icon, $description
--}}
<x-ui.card>
    <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
        <div class="flex size-12 items-center justify-center rounded-full bg-muted">
            <x-dynamic-component :component="'lucide-' . $icon" class="size-6 text-muted-foreground" />
        </div>
        <div>
            <p class="font-medium">{{ $label }}</p>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ $description }}</p>
        </div>
        <x-ui.badge variant="secondary">Coming soon</x-ui.badge>
    </div>
</x-ui.card>
