@php
    /** @var \App\Models\User $record */
    $general = [
        ['label' => 'Name', 'value' => $record->name],
        ['label' => 'Email', 'value' => $record->email],
        ['label' => 'Status', 'value' => $record->banned_at ? 'Banned' : 'Active'],
        ['label' => 'External ID', 'value' => $record->external_id, 'mono' => true],
        ['label' => 'Guest ID', 'value' => (string) $record->id, 'mono' => true],
    ];

    $dates = [
        ['label' => 'Registration date', 'value' => $record->registration_date],
        ['label' => 'Last login', 'value' => $record->last_login, 'diff' => true, 'fallback' => 'Never'],
        ['label' => 'Created at', 'value' => $record->created_at],
    ];

    $bool = fn (bool $v): string => $v ? 'Yes' : 'No';
    $status = [
        ['label' => 'Banned', 'value' => $bool($record->isBanned())],
    ];
@endphp

@php
    $section = function (string $heading, array $rows) {
        return compact('heading', 'rows');
    };
    $sections = [$section('General', $general), $section('Dates', $dates), $section('Status', $status)];

    $subscription = $record->activeSubscription;
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    @foreach ($sections as $s)
        <x-ui.card>
            <p class="mb-4 text-sm font-medium">{{ $s['heading'] }}</p>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-1">
                @foreach ($s['rows'] as $row)
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ $row['label'] }}</dt>
                        <dd class="mt-0.5 text-sm {{ ! empty($row['mono']) ? 'font-mono text-xs break-all' : '' }}">
                            @if ($row['value'] instanceof \Carbon\CarbonInterface)
                                <x-ui.local-time :value="$row['value']" :show-diff="$row['diff'] ?? false" />
                            @elseif ($row['value'] === null)
                                {{ $row['fallback'] ?? '—' }}
                            @else
                                {{ $row['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    @endforeach
</div>

{{-- Active subscription glance --}}
<x-ui.card class="mt-6 overflow-hidden">
    <div class="flex items-center justify-between border-b border-border/50 pb-4">
        <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                <x-lucide-zap class="size-4" />
            </div>
            <p class="text-sm font-semibold text-foreground">Active Subscription</p>
        </div>
        @can('guests.manage')
            <button type="button" wire:click="selectTab('subscriptions')" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                <span>Manage Subscriptions</span>
                <x-lucide-chevron-right class="size-3.5" />
            </button>
        @endcan
    </div>

    @if ($subscription)
        <div class="mt-5 rounded-xl border border-border/70 bg-gradient-to-br from-card via-card to-primary/5 p-5 shadow-2xs">
            <div class="flex flex-wrap items-center justify-between gap-4">
                {{-- Left: Plan Name with Link & Badges --}}
                <div class="flex items-center gap-3.5">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shrink-0">
                        <x-lucide-package class="size-5.5" />
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($subscription->plan)
                                <a href="{{ route('admin.plans.show', $subscription->plan) }}" class="inline-flex items-center gap-1.5 text-base font-bold text-foreground hover:text-primary transition-colors group">
                                    <span>{{ $subscription->plan->name }}</span>
                                    <x-lucide-arrow-up-right class="size-4 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                </a>
                            @else
                                <span class="text-base font-bold text-foreground">Deleted Plan</span>
                            @endif

                            @if ($subscription->plan?->is_best_deal)
                                <x-ui.badge variant="default" class="gap-1 border-0 bg-amber-500/15 text-amber-700 dark:text-amber-400 text-xs">
                                    <x-lucide-star class="size-3 fill-current" />
                                    Best Deal
                                </x-ui.badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            @if (in_array($subscription->status, [\App\Enum\SubscriptionStatus::Active, \App\Enum\SubscriptionStatus::Trialing], true))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $subscription->status->label() }}
                                </span>
                            @elseif ($subscription->status === \App\Enum\SubscriptionStatus::Grace)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    {{ $subscription->status->label() }}
                                </span>
                            @else
                                <x-ui.badge variant="secondary" class="text-xs">{{ $subscription->status->label() }}</x-ui.badge>
                            @endif
                            <span>•</span>
                            <span>Gateway: {{ $subscription->provider->label() }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: Price Display --}}
                <div class="text-right">
                    <p class="text-lg font-bold text-foreground tracking-tight">
                        {{ $subscription->planPrice->currency }} {{ number_format((float) $subscription->planPrice->amount, 2) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        / {{ $subscription->planPrice->billing_period }} {{ $subscription->planPrice->billing_interval->label() }}{{ $subscription->planPrice->billing_period > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>

            {{-- Info Grid --}}
            <div class="mt-4 grid grid-cols-2 gap-4 border-t border-border/50 pt-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">Started On</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        <x-ui.local-time :value="$subscription->starts_at" format="MMM D, YYYY" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">{{ $subscription->cancelled_by ? 'Access Until' : 'Renews On' }}</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        @if ($subscription->ends_at)
                            <x-ui.local-time :value="$subscription->ends_at" format="MMM D, YYYY" />
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">Auto-Renewal</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        {{ $subscription->is_recurring ? 'Enabled' : 'Disabled' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">Total Paid</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        {{ $subscription->amount_paid !== null ? $subscription->currency.' '.number_format((float) $subscription->amount_paid, 2) : '—' }}
                    </dd>
                </div>
            </div>
        </div>
    @else
        <div class="mt-4 flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/70 p-6 text-center">
            <div class="flex size-10 items-center justify-center rounded-full bg-muted">
                <x-lucide-credit-card class="size-5 text-muted-foreground/50" />
            </div>
            <div>
                <p class="text-sm font-medium text-foreground">No Active Subscription</p>
                <p class="text-xs text-muted-foreground">This guest is currently not subscribed to any plan.</p>
            </div>
            @can('guests.manage')
                <x-ui.button variant="outline" size="sm" wire:click="openAssignPlanDialog" class="mt-1 gap-1.5 text-xs">
                    <x-lucide-plus class="size-3.5" />
                    <span>Assign Plan</span>
                </x-ui.button>
            @endcan
        </div>
    @endif
</x-ui.card>
