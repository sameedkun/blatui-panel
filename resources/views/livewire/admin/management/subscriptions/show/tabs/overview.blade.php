@php
    use Illuminate\Support\Str;
    /** @var \App\Models\Subscription $record */
    /** @var \App\Models\Subscription|null $nextSubscription */

    $canViewSubs = auth()->user()->can('subscriptions.manage');
    $canViewUser = auth()->user()->can('users.manage');
    $canViewPlan = auth()->user()->can('plans.manage');

    $details = [
        ['label' => 'Subscription ID', 'value' => (string) $record->id, 'mono' => true],
        ['label' => 'Subscription Status', 'value' => $record->status->label(), 'badge' => true, 'status' => $record->status],
        ['label' => 'Payment Gateway', 'value' => $record->provider->label()],
        ['label' => 'Auto-Renewal State', 'value' => $record->is_recurring ? 'Enabled' : 'Disabled'],
        ['label' => 'Total Amount Paid', 'value' => $record->amount_paid !== null ? $record->currency.' '.number_format((float) $record->amount_paid, 2) : '—'],
        ['label' => 'Subscription Started', 'value' => $record->starts_at?->format('M d, Y h:i A') ?? '—'],
        ['label' => 'Trial Expiration', 'value' => $record->trial_ends_at?->format('M d, Y h:i A') ?? '—'],
        ['label' => 'Access / Renewal Date', 'value' => $record->ends_at?->format('M d, Y h:i A') ?? '—'],
        ['label' => 'Grace Expiration', 'value' => $record->grace_ends_at?->format('M d, Y h:i A') ?? '—'],
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
                        <x-ui.card-title class="text-base">Lifecycle & Billing Parameters</x-ui.card-title>
                        <x-ui.card-description>Full lifecycle timestamps, gateway details, and recurring status.</x-ui.card-description>
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
                        <dt class="text-xs font-semibold text-amber-700 dark:text-amber-400">Cancelled by {{ Str::headline($record->cancelled_by->value) }}</dt>
                        <dd class="text-xs text-amber-900 dark:text-amber-200">Reason: {{ $record->cancelled_reason ?? 'No reason provided' }}</dd>
                    </div>
                @endif

                @if (!empty($record->proration_meta))
                    <div class="mt-5 rounded-lg border border-border/60 bg-muted/20 p-4 space-y-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Upgrade Proration Meta</dt>
                        <dd class="flex flex-wrap gap-4 text-xs font-mono text-foreground">
                            @foreach ($record->proration_meta as $key => $value)
                                <span class="bg-card px-2.5 py-1 rounded border border-border/60">
                                    <span class="text-muted-foreground">{{ Str::headline($key) }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}
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
                            <x-ui.card-title class="text-base">Subscription Lineage & Chain</x-ui.card-title>
                            <x-ui.card-description>Upgrade or downgrade sequence linked to this record.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="grid grid-cols-1 gap-4 pt-6 sm:grid-cols-2">
                    @if ($record->previousSubscription)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-1.5">
                            <dt class="text-xs font-medium text-muted-foreground">Replaced (Previous Tier)</dt>
                            <dd class="text-sm font-semibold">
                                @if ($canViewSubs)
                                    <a href="{{ route('admin.subscriptions.show', $record->previousSubscription) }}" class="inline-flex items-center gap-1.5 text-primary hover:underline group">
                                        <span>{{ $record->previousSubscription->plan->name ?? 'Deleted Plan' }}</span>
                                        <x-lucide-arrow-up-right class="size-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                                    </a>
                                @else
                                    {{ $record->previousSubscription->plan->name ?? 'Deleted Plan' }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($nextSubscription)
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-1.5">
                            <dt class="text-xs font-medium text-muted-foreground">Replaced By (Next Tier)</dt>
                            <dd class="text-sm font-semibold">
                                @if ($canViewSubs)
                                    <a href="{{ route('admin.subscriptions.show', $nextSubscription) }}" class="inline-flex items-center gap-1.5 text-primary hover:underline group">
                                        <span>{{ $nextSubscription->plan->name ?? 'Deleted Plan' }}</span>
                                        <x-lucide-arrow-up-right class="size-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                                    </a>
                                @else
                                    {{ $nextSubscription->plan->name ?? 'Deleted Plan' }}
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
                        <x-ui.card-title class="text-base">Subscriber Account</x-ui.card-title>
                        <x-ui.card-description>User who holds this subscription.</x-ui.card-description>
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
                            View Full User Profile
                        </x-ui.button>
                    @endif
                @else
                    <p class="text-sm text-muted-foreground italic">Subscriber account deleted.</p>
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
                        <x-ui.card-title class="text-base">Plan & Price Point</x-ui.card-title>
                        <x-ui.card-description>Subscribed tier information.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="space-y-3.5 pt-6">
                @if ($record->plan)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Plan Name</span>
                        <span class="text-sm font-semibold text-foreground">{{ $record->plan->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Plan Visibility</span>
                        @if ($record->plan->is_active)
                            <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs">Active</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" class="text-xs">Retired</x-ui.badge>
                        @endif
                    </div>
                @endif
                @if ($record->planPrice)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Price Amount</span>
                        <span class="text-sm font-semibold text-foreground">{{ $record->planPrice->currency }} {{ number_format((float) $record->planPrice->amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Billing Interval</span>
                        <span class="text-sm font-semibold text-foreground">
                            Every {{ $record->planPrice->billing_period }} {{ $record->planPrice->billing_interval->label() }}{{ $record->planPrice->billing_period > 1 ? 's' : '' }}
                        </span>
                    </div>
                @endif
                @if ($canViewPlan && $record->plan)
                    <x-ui.button variant="outline" size="sm" href="{{ route('admin.plans.show', $record->plan) }}" class="mt-2 w-full gap-1.5 text-xs shadow-2xs">
                        <x-lucide-external-link class="size-3.5" />
                        View Plan Details
                    </x-ui.button>
                @endif
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
