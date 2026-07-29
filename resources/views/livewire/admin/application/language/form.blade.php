<div class="w-full">

    <x-admin.page-header :title="$isEditing ? __('languages.form.edit_title') : __('languages.form.create_title')" :description="$isEditing
        ? __('languages.form.edit_description')
        : __('languages.form.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('languages.title'), 'url' => route('admin.languages.index')],
            ['label' => $name],
            ['label' => __('languages.form.breadcrumb_edit')],
        ]
        : [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('languages.title'), 'url' => route('admin.languages.index')],
            ['label' => __('languages.form.breadcrumb_create')],
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
                                <x-ui.card-title class="text-base">{{ __('languages.form.locale_section') }}</x-ui.card-title>
                                <x-ui.card-description>{{ __('languages.form.locale_description') }}</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-5 pt-6">

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="name" required>{{ __('languages.fields.name') }}</x-ui.field-label>
                                <x-ui.input id="name" wire:model="name" :placeholder="__('languages.form.name_placeholder')"
                                    aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                                @error('name')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="native_name">{{ __('languages.fields.native_name') }}</x-ui.field-label>
                                <x-ui.input id="native_name" wire:model="native_name" :placeholder="__('languages.form.native_name_placeholder')" />
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.native_name_description') }}</p>
                                @error('native_name')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="code" required>{{ __('languages.fields.language_code') }}</x-ui.field-label>
                                <x-ui.input id="code" wire:model="code" :placeholder="__('languages.form.code_placeholder')" class="font-mono"
                                    aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" />
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.code_description') }}</p>
                                @error('code')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="flag">{{ __('languages.fields.flag') }}</x-ui.field-label>
                                <x-ui.input id="flag" wire:model="flag" :placeholder="__('languages.form.flag_placeholder')" maxlength="5" class="font-mono uppercase" />
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.flag_description') }}</p>
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
                                <x-ui.card-title class="text-base">{{ __('languages.form.translations_section') }}</x-ui.card-title>
                                <x-ui.card-description>{{ __('languages.form.translations_description') }}</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="pt-6">
                        <x-ui.field>
                            <x-ui.textarea id="translations" wire:model="translations" :rows="8" :max-rows="14"
                                class="font-mono text-xs leading-relaxed bg-muted/20" :placeholder="__('languages.form.translations_placeholder')"
                                aria-invalid="{{ $errors->has('translations') ? 'true' : 'false' }}" />
                            <p class="text-xs text-muted-foreground mt-1.5">{{ __('languages.form.translations_help') }}</p>
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
                                <x-ui.card-title class="text-base">{{ __('languages.form.behavior_section') }}</x-ui.card-title>
                                <x-ui.card-description>{{ __('languages.form.behavior_description') }}</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-4 pt-6">

                        {{-- Default Language Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_default" wire:model="is_default" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_default" class="cursor-pointer font-medium text-foreground text-sm">{{ __('languages.form.default_label') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.default_description') }}</p>
                            </div>
                        </div>

                        {{-- Active Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_active" wire:model="is_active" class="mt-0.5" :disabled="$is_default" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_active" class="cursor-pointer font-medium text-foreground text-sm">{{ __('languages.form.active_label') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.active_description') }}</p>
                            </div>
                        </div>

                        {{-- RTL Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_rtl" wire:model="is_rtl" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_rtl" class="cursor-pointer font-medium text-foreground text-sm">{{ __('languages.form.rtl_label') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('languages.form.rtl_description') }}</p>
                            </div>
                        </div>

                        {{-- Sort Order Field --}}
                        <x-ui.field>
                            <x-ui.field-label for="sort_order">{{ __('languages.fields.sort_order') }}</x-ui.field-label>
                            <x-ui.input type="number" id="sort_order" wire:model="sort_order" min="0" />
                            <p class="text-xs text-muted-foreground">{{ __('languages.form.sort_order_description') }}</p>
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
                            {{ $isEditing ? __('languages.actions.save_changes') : __('languages.actions.create') }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? __('languages.form.saving') : __('languages.form.creating') }}
                        </span>
                    </x-ui.button>

                    <x-ui.button variant="outline" size="lg" href="{{ route('admin.languages.index') }}" type="button" class="w-full justify-center text-muted-foreground hover:text-foreground">
                        {{ __('languages.actions.cancel') }}
                    </x-ui.button>
                </div>

            </div>

        </div>
    </form>

</div>
