@php
    /** @var \App\Models\Subscription $record */
    /** @var \App\Models\Subscription|null $nextSubscription */

    $canViewSubs = auth()->user()->can('subscriptions.manage');
    $canViewUser = auth()->user()->can('users.manage');
    $canViewPlan = auth()->user()->can('plans.manage');

    $details = [
        ['label' => __('subscriptions.overview.subscription_id'), 'value' => (string) $record->id, 'mono' => true],
        ['label' => __('subscriptions.overview.subscription_status'), 'value' => $record->status->label(), 'badge' => true, 'status' => $record->status],
        ['label' => __('subscriptions.overview.payment_gateway'), 'value' => $record->provider->label()],
        ['label' => __('subscriptions.overview.auto_renewal_state'), 'value' => $record->is_recurring ? __('subscriptions.status.enabled') : __('subscriptions.status.disabled')],
        ['label' => __('subscriptions.overview.total_amount_paid'), 'value' => $record->amount_paid !== null ? $record->currency.' '.number_format((float) $record->amount_paid, 2) : '—'],
        ['label' => __('subscriptions.overview.subscription_started'), 'value' => $record->starts_at?->translatedFormat('M d, Y h:i A') ?? '—'],
        ['label' => __('subscriptions.overview.trial_expiration'), 'value' => $record->trial_ends_at?->translatedFormat('M d, Y h:i A') ?? '—'],
        ['label' => __('subscriptions.overview.access_renewal_date'), 'value' => $record->ends_at?->translatedFormat('M d, Y h:i A') ?? '—'],
        ['label' => __('subscriptions.overview.grace_expiration'), 'value' => $record->grace_ends_at?->translatedFormat('M d, Y h:i A') ?? '—'],
    ];
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Main Column --}}
    <div class="space-y-6 lg:col-span-2">

        {{-- Subscription Details Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-receipt class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">{{ __('subscriptions.overview.lifecycle_billing') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('subscriptions.overview.lifecycle_billing_description') }}</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="pt-6">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ($details as $row)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5">
                            <dt class="text-xs font-medium text-muted-foreground">{{ $row['label'] }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-foreground">
                                @if (isset($row['badge']))
                                    <x-ui.badge variant="secondary" class="text-xs font-medium">
                                        {{ $row['value'] }}
                                    </x-ui.badge>
                                @elseif (!empty($row['mono']))
                                    <span class="rounded border border-border/50 bg-muted/80 px-2 py-0.5 font-mono text-xs text-foreground/90 select-all">{{ $row['value'] }}</span>
                                @else
                                    {{ $row['value'] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                @if ($record->cancelled_by)
                    <div class="mt-5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 space-y-1">
                        <dt class="text-xs font-semibold text-amber-700 dark:text-amber-400">{{ __('subscriptions.cancelled_by', ['actor' => $record->cancelled_by->label()]) }}</dt>
                        <dd class="text-xs text-amber-900 dark:text-amber-200">{{ __('subscriptions.cancellation_reason', ['reason' => $record->cancelled_reason ?? __('subscriptions.no_reason_provided')]) }}</dd>
                    </div>
                @endif

                @if (!empty($record->proration_meta))
                    <div class="mt-5 rounded-lg border border-border/60 bg-muted/20 p-4 space-y-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('subscriptions.overview.proration_meta') }}</dt>
                        <dd class="flex flex-wrap gap-4 text-xs font-mono text-foreground">
                            @foreach ($record->proration_meta as $key => $value)
                                <span class="bg-card px-2.5 py-1 rounded border border-border/60">
                                    <span class="text-muted-foreground">{{ __('subscriptions.proration_fields.'.$key) }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}
                                </span>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        {{-- Subscription Chain Card --}}
        @if ($record->previousSubscription || $nextSubscription)
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-git-branch class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">{{ __('subscriptions.overview.lineage') }}</x-ui.card-title>
                            <x-ui.card-description>{{ __('subscriptions.overview.lineage_description') }}</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="grid grid-cols-1 gap-4 pt-6 sm:grid-cols-2">
                    @if ($record->previousSubscription)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-1.5">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.overview.previous_tier') }}</dt>
                            <dd class="text-sm font-semibold">
                                @if ($canViewSubs)
                                    <a href="{{ route('admin.subscriptions.show', $record->previousSubscription) }}" class="inline-flex items-center gap-1.5 text-primary hover:underline group">
                                        <span>{{ $record->previousSubscription->plan->name ?? __('subscriptions.status.deleted_plan') }}</span>
                                        <x-lucide-arrow-up-right class="size-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                                    </a>
                                @else
                                    {{ $record->previousSubscription->plan->name ?? __('subscriptions.status.deleted_plan') }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($nextSubscription)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-1.5">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.overview.next_tier') }}</dt>
                            <dd class="text-sm font-semibold">
                                @if ($canViewSubs)
                                    <a href="{{ route('admin.subscriptions.show', $nextSubscription) }}" class="inline-flex items-center gap-1.5 text-primary hover:underline group">
                                        <span>{{ $nextSubscription->plan->name ?? __('subscriptions.status.deleted_plan') }}</span>
                                        <x-lucide-arrow-up-right class="size-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                                    </a>
                                @else
                                    {{ $nextSubscription->plan->name ?? __('subscriptions.status.deleted_plan') }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endif

    </div>

    {{-- Sidebar Column --}}
    <div class="space-y-6 lg:col-span-1">

        {{-- Subscriber Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-user class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">{{ __('subscriptions.overview.subscriber_account') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('subscriptions.overview.subscriber_account_description') }}</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="pt-6 space-y-4">
                @if ($record->user)
                    <div class="flex items-center gap-3 bg-muted/20 p-3 rounded-lg border border-border/60">
                        <x-ui.avatar class="size-11 shrink-0 rounded-full border border-border">
                            @if ($record->user->avatarUrl())
                                <x-ui.avatar-image :src="$record->user->avatarUrl()" :alt="$record->user->name" />
                            @endif
                            <x-ui.avatar-fallback class="font-bold text-xs">{{ strtoupper(substr($record->user->name, 0, 2)) }}</x-ui.avatar-fallback>
                        </x-ui.avatar>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-foreground">{{ $record->user->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $record->user->email }}</p>
                        </div>
                    </div>
                    @if ($canViewUser)
                        <x-ui.button variant="outline" size="sm" href="{{ route('admin.users.show', $record->user) }}" class="w-full gap-1.5 text-xs shadow-2xs">
                            <x-lucide-external-link class="size-3.5" />
                            {{ __('subscriptions.actions.view_user_profile') }}
                        </x-ui.button>
                    @endif
                @else
                    <p class="text-sm text-muted-foreground italic">{{ __('subscriptions.status.subscriber_deleted') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        {{-- Plan & Pricing Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-package class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">{{ __('subscriptions.overview.plan_price') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('subscriptions.overview.plan_price_description') }}</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="space-y-3.5 pt-6">
                @if ($record->plan)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ __('subscriptions.overview.plan_name') }}</span>
                        <span class="text-sm font-semibold text-foreground">{{ $record->plan->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ __('subscriptions.overview.plan_visibility') }}</span>
                        @if ($record->plan->is_active)
                            <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs">{{ __('subscriptions.status.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" class="text-xs">{{ __('subscriptions.status.retired') }}</x-ui.badge>
                        @endif
                    </div>
                @endif
                @if ($record->planPrice)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ __('subscriptions.overview.price_amount') }}</span>
                        <span class="text-sm font-semibold text-foreground">{{ $record->planPrice->currency }} {{ number_format((float) $record->planPrice->amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ __('subscriptions.overview.billing_interval') }}</span>
                        <span class="text-sm font-semibold text-foreground">
                            {{ __('subscriptions.overview.every', ['period' => trans_choice('subscriptions.billing_intervals.'.$record->planPrice->billing_interval->value, $record->planPrice->billing_period, ['count' => $record->planPrice->billing_period])]) }}
                        </span>
                    </div>
                @endif
                @if ($canViewPlan && $record->plan)
                    <x-ui.button variant="outline" size="sm" href="{{ route('admin.plans.show', $record->plan) }}" class="mt-2 w-full gap-1.5 text-xs shadow-2xs">
                        <x-lucide-external-link class="size-3.5" />
                        {{ __('subscriptions.actions.view_plan_details') }}
                    </x-ui.button>
                @endif
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
