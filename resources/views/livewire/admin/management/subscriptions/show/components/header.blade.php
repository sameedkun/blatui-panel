@php
    use App\Enum\SubscriptionStatus;
    /** @var \App\Models\Subscription $record */

    $liveStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
    $isLive = $show->isLive($record);
    $isReactivatable = $show->isReactivatable($record);
    $canManage = auth()->user()->can('subscriptions.manage');
    $canViewUser = auth()->user()->can('users.manage');
    $canViewPlan = auth()->user()->can('plans.manage');
@endphp

<div class="relative overflow-hidden rounded-xl border border-border bg-card p-6 shadow-sm">
    {{-- Ambient backdrop glow --}}
    <div class="pointer-events-none absolute -right-12 -top-12 size-56 rounded-full bg-primary/5 blur-3xl"></div>

    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">

        {{-- Identity: user + plan connection --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">

            {{-- Subscriber User Card --}}
            <div class="flex items-center gap-3 bg-muted/30 p-2.5 rounded-xl border border-border/60">
                <x-ui.avatar class="size-11 shrink-0 rounded-full border border-border/80 shadow-2xs">
                    @if ($record->user?->avatarUrl())
                        <x-ui.avatar-image :src="$record->user->avatarUrl()" :alt="$record->user->name" />
                    @endif
                    <x-ui.avatar-fallback class="font-bold text-xs">{{ strtoupper(substr($record->user->name ?? '?', 0, 2)) }}</x-ui.avatar-fallback>
                </x-ui.avatar>
                <div class="min-w-0 pr-2">
                    @if ($record->user)
                        @if ($canViewUser)
                            <a href="{{ route('admin.users.show', $record->user) }}" class="inline-flex items-center gap-1 truncate font-bold text-sm text-foreground hover:text-primary transition-colors group">
                                <span class="truncate">{{ $record->user->name }}</span>
                                <x-lucide-arrow-up-right class="size-3.5 shrink-0 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                            </a>
                        @else
                            <p class="truncate font-bold text-sm text-foreground">{{ $record->user->name }}</p>
                        @endif
                        <p class="truncate text-xs text-muted-foreground">{{ $record->user->email }}</p>
                    @else
                        <p class="font-semibold text-sm text-muted-foreground">{{ __('subscriptions.status.deleted_user') }}</p>
                    @endif
                </div>
            </div>

            <div class="hidden shrink-0 items-center justify-center rounded-full bg-muted p-1.5 text-muted-foreground sm:flex">
                <x-lucide-arrow-right class="size-4" />
            </div>

            {{-- Subscribed Plan Card --}}
            <div class="flex items-center gap-3 bg-muted/30 p-2.5 rounded-xl border border-border/60">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-br from-primary/15 via-primary/10 to-primary/5 text-primary shadow-xs">
                    <x-lucide-package class="size-5.5" />
                </div>
                <div class="min-w-0 space-y-0.5 pr-2">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($record->plan)
                            @if ($canViewPlan)
                                <a href="{{ route('admin.plans.show', $record->plan) }}" class="inline-flex items-center gap-1 truncate font-bold text-sm text-foreground hover:text-primary transition-colors group">
                                    <span class="truncate">{{ $record->plan->name }}</span>
                                    <x-lucide-arrow-up-right class="size-3.5 shrink-0 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                </a>
                            @else
                                <p class="truncate font-bold text-sm text-foreground">{{ $record->plan->name }}</p>
                            @endif
                        @else
                            <p class="font-semibold text-sm text-muted-foreground">{{ __('subscriptions.status.deleted_plan') }}</p>
                        @endif

                        @if (in_array($record->status, $liveStatuses, true))
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                {{ $record->status->label() }}
                            </span>
                        @elseif ($record->status === SubscriptionStatus::Failed)
                            <x-ui.badge variant="destructive" class="text-xs">{{ $record->status->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" class="text-xs">{{ $record->status->label() }}</x-ui.badge>
                        @endif
                    </div>

                    @if ($record->planPrice)
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ $record->planPrice->currency }} {{ number_format((float) $record->planPrice->amount, 2) }}
                            / {{ trans_choice('subscriptions.billing_intervals.'.$record->planPrice->billing_interval->value, $record->planPrice->billing_period, ['count' => $record->planPrice->billing_period]) }}
                            &bull; {{ $record->provider->label() }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions --}}
        @if ($canManage && ($isLive || $isReactivatable))
            <div class="flex shrink-0 flex-wrap items-center gap-2.5">
                @if ($isLive)
                    @if ($record->is_recurring)
                        <x-ui.button variant="outline" wire:click="openCancelAtPeriodEndDialog({{ $record->id }})" class="gap-1.5 shadow-2xs">
                            <x-lucide-clock class="size-4" />
                            <span>{{ __('subscriptions.actions.cancel_period_end') }}</span>
                        </x-ui.button>
                    @endif

                    <x-ui.button variant="destructive" wire:click="openCancelImmediatelyDialog({{ $record->id }})" class="gap-1.5 shadow-2xs">
                        <x-lucide-circle-slash class="size-4" />
                        <span>{{ __('subscriptions.actions.cancel_immediately') }}</span>
                    </x-ui.button>
                @elseif ($isReactivatable)
                    <x-ui.button variant="default" wire:click="reactivateRow({{ $record->id }})" class="gap-1.5 shadow-2xs">
                        <x-lucide-refresh-cw class="size-4" />
                        <span>{{ __('subscriptions.actions.reactivate_subscription') }}</span>
                    </x-ui.button>
                @endif
            </div>
        @elseif (! $isLive)
            <x-ui.badge variant="outline" class="shrink-0 gap-1.5 text-xs text-muted-foreground">
                <x-lucide-history class="size-3.5" />
                {{ __('subscriptions.status.historical_record') }}
            </x-ui.badge>
        @endif

    </div>
</div>
