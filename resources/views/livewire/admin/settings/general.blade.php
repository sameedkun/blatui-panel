<div class="space-y-6">
    <div>
        <h3 class="text-lg font-medium">{{ __('settings.pages.general_title') }}</h3>
        <p class="text-sm text-muted-foreground">{{ __('settings.general.description') }}</p>
    </div>
    <x-ui.separator />

    <form wire:submit="save" class="max-w-2xl space-y-4">
        <x-ui.field>
            <x-ui.field-label for="site_name" required>{{ __('settings.general.site_name') }}</x-ui.field-label>
            <x-ui.input id="site_name" wire:model="site_name" />
            @error('site_name')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
            <x-ui.field-description>{{ __('settings.general.site_name_description') }}</x-ui.field-description>
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="environment">{{ __('settings.general.environment') }}</x-ui.field-label>
            <x-ui.input id="environment" wire:model="environment" readonly class="cursor-not-allowed bg-muted/50 text-muted-foreground" />
            <x-ui.field-description>{{ __('settings.general.environment_description') }}</x-ui.field-description>
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="timezone">{{ __('settings.general.timezone') }}</x-ui.field-label>
            <x-ui.input id="timezone" wire:model="timezone" readonly class="cursor-not-allowed bg-muted/50 text-muted-foreground" />
        </x-ui.field>

        @can('settings.general.edit')
            <div class="flex justify-end pt-4">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove class="inline-flex items-center gap-2">
                        <x-lucide-save class="size-4" />
                        {{ __('settings.actions.save_general') }}
                    </span>
                    <span wire:loading.flex class="items-center gap-2">
                        <x-ui.spinner class="size-4" />
                        {{ __('settings.actions.saving') }}
                    </span>
                </x-ui.button>
            </div>
        @endcan
    </form>
</div>
