<div>
    <x-ui.dropdown-menu>
        <x-ui.dropdown-menu-trigger>
            <x-ui.button variant="ghost" size="icon" aria-label="Switch language" title="{{ __('common.language') ?? 'Language' }}">
                <x-lucide-languages class="size-4" aria-hidden="true" />
            </x-ui.button>
        </x-ui.dropdown-menu-trigger>

        <x-ui.dropdown-menu-content align="end" class="w-44">
            <x-ui.dropdown-menu-label class="text-xs font-semibold text-muted-foreground">
                {{ __('common.select_language') ?? 'Select Language' }}
            </x-ui.dropdown-menu-label>
            <x-ui.dropdown-menu-separator />
            @foreach ($locales as $code => $info)
                <x-ui.dropdown-menu-item
                    wire:click="switchLocale('{{ $code }}')"
                    class="flex items-center justify-between cursor-pointer text-xs"
                >
                    <span class="flex items-center gap-2">
                        <span>{{ $info['flag'] }}</span>
                        <span class="font-medium">{{ $info['native'] }}</span>
                        <span class="text-muted-foreground text-[10px]">({{ $info['name'] }})</span>
                    </span>
                    @if ($currentLocale === $code)
                        <x-lucide-check class="size-3.5 text-primary" />
                    @endif
                </x-ui.dropdown-menu-item>
            @endforeach
        </x-ui.dropdown-menu-content>
    </x-ui.dropdown-menu>
</div>
