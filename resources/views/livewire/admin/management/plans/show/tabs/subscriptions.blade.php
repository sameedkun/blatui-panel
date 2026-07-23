@php
    use App\Enum\SubscriptionStatus;
    use Illuminate\Support\Str;
    /** @var \Illuminate\Pagination\LengthAwarePaginator $subscriptions */

    $activeStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
@endphp

<x-ui.card class="p-0">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
        <p class="text-sm font-medium">All Subscriptions</p>
        <select wire:model.live="subsStatus" class="blat-select h-9 w-48 text-sm">
            <option value="">All statuses</option>
            @foreach (SubscriptionStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">User</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Price</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Status</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">Provider</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Started</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Ends</th>
                    <th class="px-4 py-3 text-right font-medium text-foreground">Paid</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($subscriptions as $subscription)
                    <tr wire:key="plan-sub-{{ $subscription->id }}" class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            @if ($subscription->user)
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="truncate font-medium">{{ $subscription->user->name }}</p>
                                        <x-ui.badge variant="outline" class="h-4 shrink-0 px-1.5 py-0 text-[10px] font-normal">
                                            {{ Str::headline($subscription->user->type->value) }}
                                        </x-ui.badge>
                                    </div>
                                    <p class="truncate text-xs text-muted-foreground">{{ $subscription->user->email }}</p>
                                </div>
                            @else
                                <span class="text-muted-foreground">Deleted user</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            @if ($subscription->planPrice)
                                {{ $subscription->planPrice->currency }} {{ number_format((float) $subscription->planPrice->amount, 2) }}
                                <span class="text-xs">/ {{ $subscription->planPrice->billing_interval->label() }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (in_array($subscription->status, $activeStatuses, true))
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                                    {{ $subscription->status->label() }}
                                </x-ui.badge>
                            @elseif ($subscription->status === SubscriptionStatus::Failed)
                                <x-ui.badge variant="destructive">{{ $subscription->status->label() }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ $subscription->status->label() }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ $subscription->provider->label() }}</td>
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            <x-ui.local-time :value="$subscription->starts_at" format="MMM D, YYYY" />
                        </td>
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            @if ($subscription->ends_at)
                                <x-ui.local-time :value="$subscription->ends_at" format="MMM D, YYYY" />
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ $subscription->amount_paid !== null ? $subscription->currency.' '.number_format((float) $subscription->amount_paid, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('subscriptions.manage')
                                    <x-admin.tooltip text="View subscription">
                                        <x-ui.button variant="ghost" size="icon" class="size-8" href="{{ route('admin.subscriptions.show', $subscription) }}">
                                            <x-lucide-eye class="size-4" />
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan
                                @can('users.manage')
                                    @if ($subscription->user)
                                        <x-admin.tooltip text="View user">
                                            <x-ui.button variant="ghost" size="icon" class="size-8" href="{{ route('admin.users.show', $subscription->user) }}">
                                                <x-lucide-external-link class="size-4" />
                                            </x-ui.button>
                                        </x-admin.tooltip>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-users class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">No subscriptions{{ $subsStatus ? ' with this status' : '' }} yet.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($subscriptions->hasPages())
        <div class="border-t border-border p-4">
            {{ $subscriptions->links('livewire.admin.partials.pagination') }}
        </div>
    @endif
</x-ui.card>
