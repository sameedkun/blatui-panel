@php
    /** @var \App\Models\Plan $record */
    $prices = $record->prices()->withCount('subscriptions')->with('providers')->orderBy('amount')->get();
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base font-semibold text-foreground">{{ __('plans.prices.configured_tiers') }}</h3>
            <p class="text-xs text-muted-foreground">{{ __('plans.prices.configured_tiers_description') }}</p>
        </div>
        @can('plans.edit')
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.plans.edit', $record) }}" class="gap-1.5 text-xs">
                <x-lucide-pencil class="size-3.5" />
                <span>{{ __('plans.actions.manage_prices') }}</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6">
        @forelse ($prices as $price)
            <x-ui.card class="overflow-hidden space-y-5">
                {{-- Price Tier Header --}}
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border/50 pb-4">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-baseline gap-2.5">
                            <span class="text-2xl font-bold tracking-tight text-foreground">
                                {{ $price->currency }} {{ number_format((float) $price->amount, 2) }}
                            </span>
                            @if ($price->compare_at_amount)
                                <span class="text-sm font-medium text-muted-foreground line-through">
                                    {{ $price->currency }} {{ number_format((float) $price->compare_at_amount, 2) }}
                                </span>
                            @endif
                            <span class="text-sm font-medium text-muted-foreground">
                                / {{ trans_choice('plans.billing_intervals.'.$price->billing_interval->value, $price->billing_period, ['count' => $price->billing_period]) }}
                            </span>
                        </div>
                    </div>

                    {{-- Badges & Status --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($price->is_active)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('plans.status.active_price') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground border border-border">
                                {{ __('plans.status.inactive') }}
                            </span>
                        @endif

                        @if ($price->trial_period > 0)
                            <x-ui.badge variant="outline" class="text-xs gap-1">
                                <x-lucide-clock class="size-3 text-muted-foreground" />
                                {{ __('plans.prices.trial', ['period' => trans_choice('plans.billing_intervals.'.$price->trial_interval->value, $price->trial_period, ['count' => $price->trial_period])]) }}
                            </x-ui.badge>
                        @endif

                        @if ($price->grace_period > 0)
                            <x-ui.badge variant="outline" class="text-xs gap-1">
                                <x-lucide-shield-alert class="size-3 text-muted-foreground" />
                                {{ __('plans.prices.grace', ['period' => trans_choice('plans.billing_intervals.'.$price->grace_interval->value, $price->grace_period, ['count' => $price->grace_period])]) }}
                            </x-ui.badge>
                        @endif

                        <x-ui.badge variant="secondary" class="text-xs font-medium gap-1">
                            <x-lucide-users class="size-3 text-muted-foreground" />
                            {{ trans_choice('plans.counts.subscribers', $price->subscriptions_count, ['count' => $price->subscriptions_count]) }}
                        </x-ui.badge>
                    </div>
                </div>

                {{-- Payment Gateways Mapping Section --}}
                <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <x-lucide-globe class="size-4 text-muted-foreground" />
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('plans.fields.payment_gateways') }}</span>
                    </div>

                    @if ($price->providers->isNotEmpty())
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($price->providers as $provider)
                                <div class="flex items-center justify-between rounded-md border border-border/80 bg-card p-3 shadow-2xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-ui.badge variant="outline" class="font-medium text-xs">
                                            {{ $provider->provider->label() }}
                                        </x-ui.badge>
                                        <span class="font-mono text-xs text-muted-foreground truncate select-all">
                                            {{ $provider->external_id }}
                                        </span>
                                    </div>
                                    @if (! $provider->is_active)
                                        <x-ui.badge variant="secondary" class="text-[10px] shrink-0 ml-2">{{ __('plans.status.inactive') }}</x-ui.badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground italic">{{ __('plans.status.no_gateways') }}</p>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-muted">
                        <x-lucide-credit-card class="size-6 text-muted-foreground/50" />
                    </div>
                    <p class="text-sm font-medium text-foreground">{{ __('plans.status.no_prices_configured') }}</p>
                    <p class="text-xs text-muted-foreground">{{ __('plans.status.no_prices_configured_help') }}</p>
                </div>
            </x-ui.card>
        @endforelse
    </div>
</div>
