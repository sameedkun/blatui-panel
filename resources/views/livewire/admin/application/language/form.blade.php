<div class="w-full">

    <x-admin.page-header :title="$isEditing ? 'Edit Language' : 'Create Language'" :description="$isEditing
        ? 'Update language details, locale codes, and translation strings.'
        : 'Add a new language available across the application.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Languages', 'url' => route('admin.languages.index')],
            ['label' => $name],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Languages', 'url' => route('admin.languages.index')],
            ['label' => 'Create'],
        ]" :back="route('admin.languages.index')" />

    <form wire:submit="save" class="mt-6">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

            {{-- Main Form Column (Left 2 cols on lg) --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Language Details Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <x-lucide-languages class="size-5" />
                            </div>
                            <div>
                                <x-ui.card-title class="text-base">Language & Locale</x-ui.card-title>
                                <x-ui.card-description>Set up display names, ISO codes, and country flags.</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-5 pt-6">

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="name" required>Display Name</x-ui.field-label>
                                <x-ui.input id="name" wire:model="name" placeholder="e.g. English, Arabic"
                                    aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                                @error('name')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="native_name">Native Name</x-ui.field-label>
                                <x-ui.input id="native_name" wire:model="native_name" placeholder="e.g. English, العربية" />
                                <p class="text-xs text-muted-foreground">Name in the native script.</p>
                                @error('native_name')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="code" required>Language Code (ISO 639-1)</x-ui.field-label>
                                <x-ui.input id="code" wire:model="code" placeholder="e.g. en, ar, fr" class="font-mono"
                                    aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" />
                                <p class="text-xs text-muted-foreground">Standard 2-letter code used for routes & locale matching.</p>
                                @error('code')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="flag">Country Flag Code</x-ui.field-label>
                                <x-ui.input id="flag" wire:model="flag" placeholder="e.g. us, gb, sa" maxlength="5" class="font-mono uppercase" />
                                <p class="text-xs text-muted-foreground">2-letter ISO country code used for flag icons.</p>
                                @error('flag')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>

                    </x-ui.card-content>
                </x-ui.card>

                {{-- Translations JSON Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <x-lucide-code class="size-5" />
                            </div>
                            <div>
                                <x-ui.card-title class="text-base">Translations Dictionary (JSON)</x-ui.card-title>
                                <x-ui.card-description>Full translation key-value JSON string for UI elements.</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="pt-6">
                        @php
                            $translationsPlaceholder = "{\n  \"welcome\": \"Welcome\",\n  \"logout\": \"Log out\"\n}";
                        @endphp
                        <x-ui.field>
                            <x-ui.textarea id="translations" wire:model="translations" :rows="8" :max-rows="14"
                                class="font-mono text-xs leading-relaxed bg-muted/20" :placeholder="$translationsPlaceholder"
                                aria-invalid="{{ $errors->has('translations') ? 'true' : 'false' }}" />
                            <p class="text-xs text-muted-foreground mt-1.5">Enter a valid JSON object mapping key names to localized text.</p>
                            @error('translations')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>
                    </x-ui.card-content>
                </x-ui.card>

            </div>

            {{-- Sidebar Column (Right 1 col on lg) --}}
            <div class="space-y-6 lg:col-span-1">

                {{-- Settings & Behavior Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <x-lucide-sliders class="size-4.5" />
                            </div>
                            <div>
                                <x-ui.card-title class="text-base">Behavior & Status</x-ui.card-title>
                                <x-ui.card-description>Language availability and display rules.</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-4 pt-6">

                        {{-- Default Language Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_default" wire:model="is_default" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_default" class="cursor-pointer font-medium text-foreground text-sm">Default Language</x-ui.label>
                                <p class="text-xs text-muted-foreground">Sets this as the application's default fallback language.</p>
                            </div>
                        </div>

                        {{-- Active Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_active" wire:model="is_active" class="mt-0.5" :disabled="$is_default" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_active" class="cursor-pointer font-medium text-foreground text-sm">Active & Selectable</x-ui.label>
                                <p class="text-xs text-muted-foreground">Visible in language switchers. Automatically enabled if default.</p>
                            </div>
                        </div>

                        {{-- RTL Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_rtl" wire:model="is_rtl" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_rtl" class="cursor-pointer font-medium text-foreground text-sm">Right-to-Left (RTL)</x-ui.label>
                                <p class="text-xs text-muted-foreground">Enables RTL text layout direction (e.g. Arabic, Hebrew).</p>
                            </div>
                        </div>

                        {{-- Sort Order Field --}}
                        <x-ui.field>
                            <x-ui.field-label for="sort_order">Sort Order</x-ui.field-label>
                            <x-ui.input type="number" id="sort_order" wire:model="sort_order" min="0" />
                            <p class="text-xs text-muted-foreground">Position in language selection dropdowns (0 = first).</p>
                            @error('sort_order')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>

                    </x-ui.card-content>
                </x-ui.card>

                {{-- Sticky Action Box --}}
                <div class="sticky top-6 rounded-xl border border-border/80 bg-card p-5 shadow-sm space-y-3">
                    <x-ui.button type="submit" size="lg" class="w-full justify-center shadow-xs font-semibold gap-2" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? 'Save Changes' : 'Create Language' }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? 'Saving…' : 'Creating…' }}
                        </span>
                    </x-ui.button>

                    <x-ui.button variant="outline" size="lg" href="{{ route('admin.languages.index') }}" type="button" class="w-full justify-center text-muted-foreground hover:text-foreground">
                        Cancel
                    </x-ui.button>
                </div>

            </div>

        </div>
    </form>

</div>
