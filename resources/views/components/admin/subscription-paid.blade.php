@props(['subscription'])

@php
    /**
     * What was actually collected on a subscription row — net of refunds, one
     * figure per currency (never summed across currencies). A grant with no
     * transactions says so instead of showing a fake amount. Eager-load
     * `transactions` when rendering this in a list.
     *
     * @var \App\Models\Subscription $subscription
     */
    $paid = $subscription->netPaid();
@endphp

<span {{ $attributes->class('tabular-nums') }}>
    @if ($paid !== [])
        {{ collect($paid)->map(fn ($money) => $money->format())->implode(' · ') }}
    @elseif ($subscription->isGrant())
        <x-ui.badge variant="outline" class="text-xs font-normal">{{ $subscription->source->label() }}</x-ui.badge>
    @else
        —
    @endif
</span>
