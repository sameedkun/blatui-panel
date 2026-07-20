<div class="space-y-6">
    <div>
        <h3 class="text-lg font-medium">General Settings</h3>
        <p class="text-sm text-muted-foreground">Configure the core parameters of your application dashboard.</p>
    </div>
    <x-ui.separator />

    <form wire:submit="save" class="space-y-4 max-w-2xl">
        <x-ui.field>
            <x-ui.field-label for="site_name" required>Site Name</x-ui.field-label>
            <x-ui.input id="site_name" wire:model="site_name" />
            @error('site_name')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
            <x-ui.field-description>The name of this administration portal.</x-ui.field-description>
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="environment">Environment</x-ui.field-label>
            <x-ui.input id="environment" wire:model="environment" readonly class="bg-muted/50 cursor-not-allowed text-muted-foreground" />
            <x-ui.field-description>Current application environment mode.</x-ui.field-description>
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="timezone">Default Timezone</x-ui.field-label>
            <x-ui.input id="timezone" wire:model="timezone" readonly class="bg-muted/50 cursor-not-allowed text-muted-foreground" />
        </x-ui.field>

        @can('settings.edit')
            <div class="flex justify-end pt-4">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove class="inline-flex items-center gap-2">
                        <x-lucide-save class="size-4" />
                        Save General Settings
                    </span>
                    <span wire:loading.flex class="items-center gap-2">
                        <x-ui.spinner class="size-4" />
                        Saving...
                    </span>
                </x-ui.button>
            </div>
        @endcan
    </form>
</div>
