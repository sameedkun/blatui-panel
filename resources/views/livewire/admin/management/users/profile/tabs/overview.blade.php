@php
    use Illuminate\Support\Str;
    /** @var \App\Models\User $record */

    $general = [
        ['label' => __('users.fields.name'), 'value' => $record->name],
        ['label' => __('users.fields.email'), 'value' => $record->email],
        ['label' => __('users.fields.type'), 'value' => Str::headline($record->type->value)],
        [
            'label' => __('common.status'),
            'value' => $record->banned_at ? __('users.status_labels.banned') : __('users.status_labels.active'),
        ],
        ['label' => __('users.fields.external_id'), 'value' => $record->external_id, 'mono' => true],
        ['label' => 'User ID', 'value' => (string) $record->id, 'mono' => true],
    ];

    $dates = [
        ['label' => __('users.fields.registered'), 'value' => $record->registration_date],
        [
            'label' => __('users.fields.last_login'),
            'value' => $record->last_login,
            'diff' => true,
            'fallback' => __('users.status_labels.never'),
        ],
        [
            'label' => __('users.fields.password_changed'),
            'value' => $record->password_changed_at,
            'fallback' => __('users.status_labels.never'),
        ],
        [
            'label' => __('users.fields.email_verified_at'),
            'value' => $record->email_verified_at,
            'fallback' => __('users.status_labels.unverified'),
        ],
        ['label' => __('common.created_at'), 'value' => $record->created_at],
    ];

    $bool = fn(bool $v): string => $v ? __('common.yes') : __('common.no');
    $status = [
        ['label' => __('users.status_labels.verified'), 'value' => $bool($record->hasVerifiedEmail())],
        ['label' => __('users.status_labels.banned'), 'value' => $bool($record->isBanned())],
        ['label' => __('users.tabs.pending'), 'value' => $bool($record->isPendingDeletion())],
    ];
@endphp

@php
    $section = function (string $heading, array $rows) {
        return compact('heading', 'rows');
    };
    $sections = [
        $section(__('users.overview.general'), $general),
        $section(__('users.overview.dates'), $dates),
        $section(__('common.status'), $status),
    ];

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
                        <dd class="mt-0.5 text-sm {{ !empty($row['mono']) ? 'font-mono text-xs break-all' : '' }}">
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
            <div
                class="flex size-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                <x-lucide-zap class="size-4" />
            </div>
            <p class="text-sm font-semibold text-foreground">{{ __('subscriptions.active_subscription') }}</p>
        </div>
        @can('users.manage')
            <button type="button" wire:click="selectTab('subscriptions')"
                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                <span>{{ __('subscriptions.manage_subscriptions') }}</span>
                <x-lucide-chevron-right class="size-3.5" />
            </button>
        @endcan
    </div>

    @if ($subscription)
        <div
            class="mt-5 rounded-xl border border-border/70 bg-gradient-to-br from-card via-card to-primary/5 p-5 shadow-2xs">
            <div class="flex flex-wrap items-center justify-between gap-4">
                {{-- Left: Plan Name with Link & Badges --}}
                <div class="flex items-center gap-3.5">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shrink-0">
                        <x-lucide-package class="size-5.5" />
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($subscription->plan)
                                @can('plans.manage')
                                    <a href="{{ route('admin.plans.show', $subscription->plan) }}"
                                        class="inline-flex items-center gap-1.5 text-base font-bold text-foreground hover:text-primary transition-colors group">
                                        <span>{{ $subscription->plan->name }}</span>
                                        <x-lucide-arrow-up-right
                                            class="size-4 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                    </a>
                                @else
                                    <span class="text-base font-bold text-foreground">{{ $subscription->plan->name }}</span>
                                @endcan
                            @else
                                <span
                                    class="text-base font-bold text-foreground">{{ __('common.deleted_plan') }}</span>
                            @endif

                            @if ($subscription->plan?->is_best_deal)
                                <x-ui.badge variant="default"
                                    class="gap-1 border-0 bg-amber-500/15 text-amber-700 dark:text-amber-400 text-xs">
                                    <x-lucide-star class="size-3 fill-current" />
                                    {{ __('subscriptions.best_deal') }}
                                </x-ui.badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            @if (in_array(
                                    $subscription->status,
                                    [\App\Enum\SubscriptionStatus::Active, \App\Enum\SubscriptionStatus::Trialing],
                                    true))
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $subscription->status->label() }}
                                </span>
                            @elseif ($subscription->status === \App\Enum\SubscriptionStatus::Grace)
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    {{ $subscription->status->label() }}
                                </span>
                            @else
                                <x-ui.badge variant="secondary"
                                    class="text-xs">{{ $subscription->status->label() }}</x-ui.badge>
                            @endif
                            <span>•</span>
                            <span>{{ __('subscriptions.gateway', ['provider' => $subscription->provider->label()]) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: Price Display --}}
                <div class="text-right">
                    <p class="text-lg font-bold text-foreground tracking-tight">
                        {{ $subscription->planPrice->currency }}
                        {{ number_format((float) $subscription->planPrice->amount, 2) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        / {{ $subscription->planPrice->billing_period }}
                        {{ $subscription->planPrice->billing_interval->label() }}{{ $subscription->planPrice->billing_period > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>

            {{-- Info Grid --}}
            <div class="mt-4 grid grid-cols-2 gap-4 border-t border-border/50 pt-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.started_on') }}</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        <x-ui.local-time :value="$subscription->starts_at" format="MMM D, YYYY" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        {{ $subscription->cancelled_by ? __('subscriptions.access_until') : __('subscriptions.renews_on') }}
                    </dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        @if ($subscription->ends_at)
                            <x-ui.local-time :value="$subscription->ends_at" format="MMM D, YYYY" />
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.auto_renewal') }}</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        {{ $subscription->is_recurring ? __('common.enabled') : __('common.disabled') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">{{ __('subscriptions.total_paid') }}</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-foreground">
                        {{ $subscription->amount_paid !== null ? $subscription->currency . ' ' . number_format((float) $subscription->amount_paid, 2) : '—' }}
                    </dd>
                </div>
            </div>
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
