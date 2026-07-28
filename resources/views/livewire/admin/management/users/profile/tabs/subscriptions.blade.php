@php
    use App\Enum\SubscriptionStatus;
    use Illuminate\Support\Str;
    /** @var \App\Models\User $record */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $subscriptionHistory */
    /** @var \App\Models\Subscription|null $reactivatable */

    $active = $record->activeSubscription;
    $liveStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
@endphp

<div class="flex flex-col gap-6">

    {{-- Current subscription --}}
    <x-ui.card class="overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/50 pb-4">
            <div class="flex items-center gap-2.5">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                    <x-lucide-credit-card class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground">{{ __('subscriptions.active_subscription') }}</h3>
                    <p class="text-xs text-muted-foreground">{{ __('subscriptions.active_subscription_desc') }}</p>
                </div>
            </div>

            @can('users.manage')
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button variant="outline" size="sm" wire:click="openAssignPlanDialog"
                        class="gap-1.5 text-xs shadow-2xs">
                        <x-lucide-credit-card class="size-3.5" />
                        <span>{{ __('users.actions.assign_plan') }}</span>
                    </x-ui.button>

                    @if ($active)
                        @if ($active->is_recurring)
                            <x-ui.button variant="outline" size="sm" wire:click="openCancelAtPeriodEndDialog"
                                class="gap-1.5 text-xs shadow-2xs">
                                <x-lucide-clock class="size-3.5" />
                                <span>{{ __('users.actions.cancel_at_period_end') }}</span>
                            </x-ui.button>
                        @endif

                        <x-ui.button variant="destructive" size="sm" wire:click="openCancelImmediatelyDialog"
                            class="gap-1.5 text-xs shadow-2xs">
                            <x-lucide-circle-slash class="size-3.5" />
                            <span>{{ __('users.actions.cancel_immediately') }}</span>
                        </x-ui.button>
                    @elseif ($reactivatable)
                        <x-ui.button variant="outline" size="sm" wire:click="reactivateSubscription"
                            class="gap-1.5 text-xs shadow-2xs">
                            <x-lucide-refresh-cw class="size-3.5" />
                            <span>{{ __('subscriptions.actions.reactivate') }}</span>
                        </x-ui.button>
                    @endif
                </div>
            @endcan
        </div>

        @if ($active)
            <div class="mt-5 space-y-5">
                {{-- Banner Highlight --}}
                <div
                    class="rounded-xl border border-border/70 bg-gradient-to-br from-card via-card to-primary/5 p-5 shadow-2xs">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        {{-- Plan Identity & Link --}}
                        <div class="flex items-center gap-3.5">
                            <div
                                class="flex size-12 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shrink-0">
                                <x-lucide-package class="size-6" />
                            </div>
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($active->plan)
                                        <a href="{{ route('admin.plans.show', $active->plan) }}"
                                            class="inline-flex items-center gap-1.5 text-lg font-bold text-foreground hover:text-primary transition-colors group">
                                            <span>{{ $active->plan->name }}</span>
                                            <x-lucide-arrow-up-right
                                                class="size-4 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                        </a>
                                    @else
                                        <span class="text-lg font-bold text-foreground">{{ __('common.deleted_plan') }}</span>
                                    @endif

                                    @if ($active->plan?->is_best_deal)
                                        <x-ui.badge variant="default"
                                            class="gap-1 border-0 bg-amber-500/15 text-amber-700 dark:text-amber-400 text-xs font-medium">
                                            <x-lucide-star class="size-3 fill-current" />
                                            Best Deal
                                        </x-ui.badge>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    @if (in_array($active->status, $liveStatuses, true))
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            {{ $active->status->label() }}
                                        </span>
                                    @elseif ($active->status === SubscriptionStatus::Grace)
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                                            {{ $active->status->label() }}
                                        </span>
                                    @else
                                        <x-ui.badge variant="secondary"
                                            class="text-xs">{{ $active->status->label() }}</x-ui.badge>
                                    @endif
                                    <span>•</span>
                                    <span>{{ __('subscriptions.gateway', ['provider' => $active->provider->label()]) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Price Badge --}}
                        <div class="text-right">
                            <p class="text-xl font-bold tracking-tight text-foreground">
                                {{ $active->planPrice->currency }}
                                {{ number_format((float) $active->planPrice->amount, 2) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                / {{ $active->planPrice->billing_period }}
                                {{ $active->planPrice->billing_interval->label() }}{{ $active->planPrice->billing_period > 1 ? 's' : '' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Parameter Cards Grid --}}
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4">
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.fields.starts_at') }}</dt>
                        <dd class="text-xs font-semibold text-foreground">
                            <x-ui.local-time :value="$active->starts_at" format="MMM D, YYYY" />
                        </dd>
                    </div>

                    @if ($active->trial_ends_at)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.fields.trial_ends_at') }}</dt>
                            <dd class="text-xs font-semibold text-foreground">
                                <x-ui.local-time :value="$active->trial_ends_at" format="MMM D, YYYY" />
                            </dd>
                        </div>
                    @endif

                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <dt class="text-xs font-medium text-muted-foreground">
                            {{ $active->cancelled_by ? __('subscriptions.access_until') : __('subscriptions.renews_on') }}</dt>
                        <dd class="text-xs font-semibold text-foreground">
                            @if ($active->ends_at)
                                <x-ui.local-time :value="$active->ends_at" format="MMM D, YYYY" />
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    @if ($active->grace_ends_at)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.fields.grace_ends_at') }}</dt>
                            <dd class="text-xs font-semibold text-foreground">
                                <x-ui.local-time :value="$active->grace_ends_at" format="MMM D, YYYY" />
                            </dd>
                        </div>
                    @endif

                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.auto_renewal') }}</dt>
                        <dd class="text-xs font-semibold text-foreground">
                            {{ $active->is_recurring ? __('common.enabled') : __('common.disabled') }}
                        </dd>
                    </div>

                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.total_paid') }}</dt>
                        <dd class="text-xs font-semibold text-foreground">
                            {{ $active->amount_paid !== null ? $active->currency . ' ' . number_format((float) $active->amount_paid, 2) : '—' }}
                        </dd>
                    </div>

                    @if ($active->cancelled_by)
                        <div
                            class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3.5 space-y-1 sm:col-span-2">
                            <dt class="text-xs font-medium text-amber-700 dark:text-amber-400">Cancelled By
                                {{ Str::headline($active->cancelled_by->value) }}</dt>
                            <dd class="text-xs text-amber-900 dark:text-amber-200">
                                Reason: {{ $active->cancelled_reason ?? 'No reason provided' }}
                            </dd>
                        </div>
                    @endif
                </div>
            </div>
        @elseif ($reactivatable)
            <div
                class="mt-4 flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200">
                <x-lucide-info class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="text-xs font-medium">
                    Subscription cancelled — access remains until <x-ui.local-time :value="$reactivatable->ends_at"
                        format="MMM D, YYYY" />. You can reactivate this subscription before then.
                </p>
            </div>
        @else
            <div
                class="mt-4 flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/70 p-6 text-center">
                <div class="flex size-10 items-center justify-center rounded-full bg-muted">
                    <x-lucide-credit-card class="size-5 text-muted-foreground/50" />
                </div>
                <div>
                    <p class="text-sm font-medium text-foreground">{{ __('subscriptions.no_active') }}</p>
                    <p class="text-xs text-muted-foreground">{{ __('subscriptions.no_active_desc') }}</p>
                </div>
                @can('users.manage')
                    <x-ui.button variant="outline" size="sm" wire:click="openAssignPlanDialog"
                        class="mt-1 gap-1.5 text-xs">
                        <x-lucide-plus class="size-3.5" />
                        <span>{{ __('users.actions.assign_plan') }}</span>
                    </x-ui.button>
                @endcan
            </div>
        @endif
    </x-ui.card>

    {{-- History --}}
    <x-ui.card class="p-0 overflow-hidden">
        <div class="border-b border-border/50 p-4">
            <h3 class="text-sm font-semibold text-foreground">{{ __('subscriptions.history') }}</h3>
            <p class="text-xs text-muted-foreground">{{ __('subscriptions.history_desc', ['name' => $record->name]) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <th class="px-4 py-3 text-left font-semibold">{{ __('subscriptions.fields.plan') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('subscriptions.fields.status') }}</th>
                        <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">{{ __('subscriptions.fields.starts_at') }}</th>
                        <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">{{ __('subscriptions.fields.ends_at') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('subscriptions.total_paid') }}</th>
                        <th class="w-10 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse ($subscriptionHistory as $subscription)
                        <tr wire:key="user-sub-{{ $subscription->id }}" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="space-y-0.5">
                                    @if ($subscription->plan)
                                        <a href="{{ route('admin.plans.show', $subscription->plan) }}"
                                            class="inline-flex items-center gap-1 font-semibold text-foreground hover:text-primary transition-colors group">
                                            <span>{{ $subscription->plan->name }}</span>
                                            <x-lucide-arrow-up-right
                                                class="size-3.5 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                        </a>
                                    @else
                                        <span class="font-semibold text-foreground">—</span>
                                    @endif

                                    @if ($subscription->planPrice)
                                        <p class="text-xs text-muted-foreground">
                                            {{ $subscription->planPrice->currency }}
                                            {{ number_format((float) $subscription->planPrice->amount, 2) }}
                                            / {{ $subscription->planPrice->billing_interval->label() }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                @if (in_array($subscription->status, $liveStatuses, true))
                                    <x-ui.badge variant="default"
                                        class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $subscription->status->label() }}</x-ui.badge>
                                @elseif ($subscription->status === SubscriptionStatus::Failed)
                                    <x-ui.badge
                                        variant="destructive">{{ $subscription->status->label() }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="secondary">{{ $subscription->status->label() }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3.5 text-xs text-muted-foreground md:table-cell">
                                <x-ui.local-time :value="$subscription->starts_at" format="MMM D, YYYY" />
                            </td>
                            <td class="hidden px-4 py-3.5 text-xs text-muted-foreground md:table-cell">
                                @if ($subscription->ends_at)
                                    <x-ui.local-time :value="$subscription->ends_at" format="MMM D, YYYY" />
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-xs font-semibold text-foreground">
                                {{ $subscription->amount_paid !== null ? $subscription->currency . ' ' . number_format((float) $subscription->amount_paid, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @can('subscriptions.manage')
                                    <x-admin.tooltip text="View subscription">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.subscriptions.show', $subscription) }}">
                                            <x-lucide-eye class="size-4" />
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-lucide-credit-card class="size-8 text-muted-foreground/30" />
                                    <p class="text-sm font-medium">{{ __('subscriptions.no_history') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subscriptionHistory->hasPages())
            <div class="border-t border-border p-4">
                {{ $subscriptionHistory->links('livewire.admin.partials.pagination') }}
            </div>
        @endif
    </x-ui.card>

</div>
