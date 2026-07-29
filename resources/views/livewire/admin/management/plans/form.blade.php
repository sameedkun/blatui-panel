<div class="w-full">

    <x-admin.page-header :title="$isEditing ? __('plans.actions.edit') : __('plans.actions.create')" :description="$isEditing
        ? __('plans.form.edit_description')
        : __('plans.form.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('plans.title'), 'url' => route('admin.plans.index')],
            ['label' => Str::limit($name, 30)],
            ['label' => __('plans.actions.edit_short')],
        ]
        : [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('plans.title'), 'url' => route('admin.plans.index')],
            ['label' => __('plans.actions.create_short')],
        ]" :back="route('admin.plans.index')" />

    <form wire:submit="save" class="mt-6">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

            {{-- Main Form Column (Left 2 cols on lg) --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Plan Details Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <x-lucide-package class="size-5" />
                            </div>
                            <div>
                                <x-ui.card-title class="text-base">{{ __('plans.form.plan_details') }}</x-ui.card-title>
                                <x-ui.card-description>{{ __('plans.form.plan_details_description') }}</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-5 pt-6">
                        <x-ui.field>
                            <x-ui.field-label for="name" required>{{ __('plans.fields.name') }}</x-ui.field-label>
                            <x-ui.input id="name" wire:model="name" :placeholder="__('plans.placeholders.name')"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                            @error('name')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>

                        <x-ui.field>
                            <x-ui.field-label for="description">{{ __('plans.fields.description') }}</x-ui.field-label>
                            <x-ui.textarea id="description" wire:model="description" rows="3"
                                :placeholder="__('plans.placeholders.description')" />
                            <p class="text-xs text-muted-foreground">{{ __('plans.form.description_help') }}</p>
                            @error('description')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>
                    </x-ui.card-content>
                </x-ui.card>

                {{-- Pricing & Billing Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                    <x-lucide-credit-card class="size-5" />
                                </div>
                                <div>
                                    <x-ui.card-title class="text-base">{{ __('plans.form.pricing_billing') }}</x-ui.card-title>
                                    <x-ui.card-description>{{ __('plans.form.pricing_billing_description') }}</x-ui.card-description>
                                </div>
                            </div>
                            <x-ui.badge variant="secondary" class="font-medium">
                                {{ trans_choice('plans.counts.prices', count($prices), ['count' => count($prices)]) }}
                            </x-ui.badge>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-6 pt-6">
                        @foreach ($prices as $i => $price)
                            <div wire:key="price-{{ $i }}" class="relative rounded-xl border border-border/80 bg-card p-5 shadow-xs space-y-5 transition-all hover:border-border">
                                
                                {{-- Price Header --}}
                                <div class="flex items-center justify-between border-b border-border/40 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <x-ui.badge variant="secondary" class="font-medium text-xs">
                                            {{ __('plans.form.price_number', ['number' => $i + 1]) }}
                                        </x-ui.badge>

                                        @if (!empty($price['is_active']))
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                {{ __('plans.status.active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground border border-border">
                                                {{ __('plans.status.inactive') }}
                                            </span>
                                        @endif
                                    </div>

                                    <x-ui.button variant="ghost" size="sm" type="button" class="h-8 text-xs text-destructive hover:text-destructive hover:bg-destructive/10 gap-1.5"
                                        wire:click="removePrice({{ $i }})">
                                        <x-lucide-trash-2 class="size-3.5" />
                                        <span>{{ __('plans.actions.remove_price') }}</span>
                                    </x-ui.button>
                                </div>

                                {{-- Price Financials Grid --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <x-ui.field>
                                        <x-ui.field-label required>{{ __('plans.fields.amount') }}</x-ui.field-label>
                                        <x-ui.input type="number" step="0.01" min="0" wire:model="prices.{{ $i }}.amount" placeholder="9.99" />
                                        @error('prices.'.$i.'.amount')
                                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                        @enderror
                                    </x-ui.field>

                                    <x-ui.field>
                                        <x-ui.field-label>{{ __('plans.fields.compare_at_amount') }}</x-ui.field-label>
                                        <x-ui.input type="number" step="0.01" min="0" wire:model="prices.{{ $i }}.compare_at_amount" :placeholder="__('plans.placeholders.compare_at_amount')" />
                                        @error('prices.'.$i.'.compare_at_amount')
                                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                        @enderror
                                    </x-ui.field>

                                    <x-ui.field>
                                        <x-ui.field-label required>{{ __('plans.fields.currency') }}</x-ui.field-label>
                                        <x-ui.input wire:model="prices.{{ $i }}.currency" maxlength="3" class="uppercase font-mono tracking-wider" placeholder="USD" />
                                        @error('prices.'.$i.'.currency')
                                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                        @enderror
                                    </x-ui.field>
                                </div>

                                {{-- Billing Periods Grid --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <x-ui.field>
                                        <x-ui.field-label required>{{ __('plans.fields.billing_cycle') }}</x-ui.field-label>
                                        <div class="flex gap-2">
                                            <x-ui.input type="number" min="1" wire:model="prices.{{ $i }}.billing_period" class="w-20 shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <x-ui.select native wire:model="prices.{{ $i }}.billing_interval" :options="$billingIntervalOptions" />
                                            </div>
                                        </div>
                                        @error('prices.'.$i.'.billing_period')
                                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                        @enderror
                                        @error('prices.'.$i.'.billing_interval')
                                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                        @enderror
                                    </x-ui.field>

                                    <x-ui.field>
                                        <x-ui.field-label>{{ __('plans.fields.trial_period') }}</x-ui.field-label>
                                        <div class="flex gap-2">
                                            <x-ui.input type="number" min="0" wire:model="prices.{{ $i }}.trial_period" class="w-20 shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <x-ui.select native wire:model="prices.{{ $i }}.trial_interval" :options="$billingIntervalOptions" />
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('plans.form.no_trial_help') }}</p>
                                    </x-ui.field>

                                    <x-ui.field>
                                        <x-ui.field-label>{{ __('plans.fields.grace_period') }}</x-ui.field-label>
                                        <div class="flex gap-2">
                                            <x-ui.input type="number" min="0" wire:model="prices.{{ $i }}.grace_period" class="w-20 shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <x-ui.select native wire:model="prices.{{ $i }}.grace_interval" :options="$billingIntervalOptions" />
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('plans.form.no_grace_help') }}</p>
                                    </x-ui.field>
                                </div>

                                {{-- Active Price Toggle --}}
                                <div class="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3.5 py-2.5">
                                    <x-ui.checkbox id="price-{{ $i }}-active" wire:model="prices.{{ $i }}.is_active" class="mt-0.5" />
                                    <x-ui.label for="price-{{ $i }}-active" class="cursor-pointer text-xs font-medium text-foreground select-none">
                                        {{ __('plans.form.enable_price') }}
                                    </x-ui.label>
                                </div>

                                {{-- Payment Providers Sub-Card --}}
                                <div class="rounded-lg border border-border/60 bg-muted/30 p-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <x-lucide-globe class="size-4 text-muted-foreground" />
                                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('plans.fields.payment_gateways') }}</span>
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ trans_choice('plans.counts.gateways', count($price['providers']), ['count' => count($price['providers'])]) }}</span>
                                    </div>

                                    <div class="space-y-2.5">
                                        @foreach ($price['providers'] as $j => $provider)
                                            <div wire:key="price-{{ $i }}-provider-{{ $j }}" class="flex flex-wrap items-center gap-2.5 rounded-md border border-border/80 bg-card p-2.5 shadow-2xs">
                                                <div class="w-36 shrink-0">
                                                    <x-ui.select native size="sm" wire:model="prices.{{ $i }}.providers.{{ $j }}.provider" :options="$paymentProviderOptions" />
                                                </div>
                                                <div class="flex-1 min-w-[200px]">
                                                    <x-ui.input wire:model="prices.{{ $i }}.providers.{{ $j }}.external_id" :placeholder="__('plans.placeholders.external_id')" class="h-8 text-xs font-mono" />
                                                    @error('prices.'.$i.'.providers.'.$j.'.external_id')
                                                        <x-ui.field-error class="text-xs">{{ $message }}</x-ui.field-error>
                                                    @enderror
                                                </div>
                                                <label class="flex shrink-0 items-center gap-1.5 px-2 text-xs font-medium text-muted-foreground cursor-pointer select-none">
                                                    <x-ui.checkbox wire:model="prices.{{ $i }}.providers.{{ $j }}.is_active" />
                                                    {{ __('plans.status.active') }}
                                                </label>
                                                <x-ui.button variant="ghost" size="icon" type="button" class="size-8 shrink-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                    wire:click="removeProvider({{ $i }}, {{ $j }})">
                                                    <x-lucide-x class="size-4" />
                                                </x-ui.button>
                                            </div>
                                        @endforeach
                                    </div>

                                    <x-ui.button variant="outline" size="sm" type="button" class="mt-2 text-xs gap-1.5" wire:click="addProvider({{ $i }})">
                                        <x-lucide-plus class="size-3.5" />
                                        {{ __('plans.actions.add_gateway') }}
                                    </x-ui.button>
                                </div>

                            </div>
                        @endforeach

                        {{-- Add Price Button --}}
                        <x-ui.button variant="outline" type="button" wire:click="addPrice"
                            class="w-full py-5 border-dashed border-border hover:border-primary/50 hover:bg-primary/5 text-muted-foreground hover:text-foreground gap-2 transition-all">
                            <x-lucide-plus class="size-4" />
                            <span>{{ __('plans.actions.add_price') }}</span>
                        </x-ui.button>
                    </x-ui.card-content>
                </x-ui.card>

            </div>

            {{-- Sidebar Column (Right 1 col on lg) --}}
            <div class="space-y-6 lg:col-span-1">

                {{-- Status & Settings Card --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <x-lucide-sliders class="size-4.5" />
                            </div>
                            <div>
                                <x-ui.card-title class="text-base">{{ __('plans.form.visibility_order') }}</x-ui.card-title>
                                <x-ui.card-description>{{ __('plans.form.visibility_order_description') }}</x-ui.card-description>
                            </div>
                        </div>
                    </x-ui.card-header>
                    <x-ui.card-content class="space-y-4 pt-6">
                        {{-- Active Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_active" wire:model="is_active" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_active" class="cursor-pointer font-medium text-foreground text-sm">{{ __('plans.form.active_plan') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('plans.form.active_plan_help') }}</p>
                            </div>
                        </div>

                        {{-- Best Deal Switch Tile --}}
                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3.5 shadow-2xs transition-colors hover:border-border">
                            <x-ui.checkbox id="is_best_deal" wire:model="is_best_deal" class="mt-0.5" />
                            <div class="space-y-0.5">
                                <x-ui.label for="is_best_deal" class="cursor-pointer font-medium text-foreground text-sm">{{ __('plans.form.best_deal_highlight') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('plans.form.best_deal_help') }}</p>
                            </div>
                        </div>

                        {{-- Sort Order Field --}}
                        <x-ui.field>
                            <x-ui.field-label for="sort_order">{{ __('plans.fields.sort_order') }}</x-ui.field-label>
                            <x-ui.input id="sort_order" type="number" min="0" wire:model="sort_order" />
                            <p class="text-xs text-muted-foreground">{{ __('plans.form.sort_order_help') }}</p>
                            @error('sort_order')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>
                    </x-ui.card-content>
                </x-ui.card>

                {{-- Features & Entitlements Card --}}
                @if (count(config('panel.features', [])))
                    <x-ui.card>
                        <x-ui.card-header class="border-b border-border/50 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                    <x-lucide-sparkles class="size-4.5" />
                                </div>
                                <div>
                                    <x-ui.card-title class="text-base">{{ __('plans.form.features_entitlements') }}</x-ui.card-title>
                                    <x-ui.card-description>{{ __('plans.form.features_entitlements_description') }}</x-ui.card-description>
                                </div>
                            </div>
                        </x-ui.card-header>
                        <x-ui.card-content class="space-y-4 pt-6">
                            @foreach (config('panel.features', []) as $key => $feature)
                                <x-ui.field>
                                    @if (($feature['type'] ?? 'string') === 'boolean')
                                        <div class="flex items-start gap-3 rounded-lg border border-border/80 bg-card p-3 shadow-2xs">
                                            <x-ui.checkbox id="feature-{{ $key }}" wire:model="features.{{ $key }}" class="mt-0.5" />
                                            <div class="space-y-0.5">
                                                <x-ui.label for="feature-{{ $key }}" class="cursor-pointer font-medium text-foreground text-xs">
                                                    {{ __('plans.feature_names.'.$key) }}
                                                </x-ui.label>
                                                <p class="text-[11px] text-muted-foreground">{{ __('plans.form.grant_feature') }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <x-ui.field-label for="feature-{{ $key }}" class="text-xs">{{ __('plans.feature_names.'.$key) }}</x-ui.field-label>
                                        <x-ui.input id="feature-{{ $key }}"
                                            type="{{ ($feature['type'] ?? 'string') === 'integer' ? 'number' : 'text' }}"
                                            wire:model="features.{{ $key }}"
                                            class="h-9 text-xs" />
                                    @endif
                                    @error('features.'.$key)
                                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                    @enderror
                                </x-ui.field>
                            @endforeach
                        </x-ui.card-content>
                    </x-ui.card>
                @endif

                {{-- Sticky Action Box --}}
                <div class="sticky top-6 rounded-xl border border-border/80 bg-card p-5 shadow-sm space-y-3">
                    <x-ui.button type="submit" size="lg" class="w-full justify-center shadow-xs font-semibold gap-2" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? __('plans.actions.save_changes') : __('plans.actions.create') }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? __('plans.actions.saving') : __('plans.actions.creating') }}
                        </span>
                    </x-ui.button>

                    <x-ui.button variant="outline" size="lg" href="{{ route('admin.plans.index') }}" type="button" class="w-full justify-center text-muted-foreground hover:text-foreground">
                        {{ __('plans.actions.cancel') }}
                    </x-ui.button>
                </div>

            </div>

        </div>
    </form>

</div>
