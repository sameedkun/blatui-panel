{{-- Single-subscription action dialogs, shared by the Subscriptions index and
     the subscription profile page — included by both, sharing their Livewire scope. --}}

<x-admin.reason-dialog
    id="cancel-immediately"
    :title="__('subscriptions.dialogs.cancel_immediately_title')"
    :description="__('subscriptions.dialogs.cancel_immediately_description')"
    model="cancelReason"
    confirm="cancelImmediately"
    :confirm-label="__('subscriptions.actions.cancel_immediately')"
    variant="destructive"
    cancel="$wire.set('targetSubscriptionId', null)"
    :placeholder="__('subscriptions.placeholders.reason_optional')"
/>

<x-admin.reason-dialog
    id="cancel-at-period-end"
    :title="__('subscriptions.dialogs.cancel_period_end_title')"
    :description="__('subscriptions.dialogs.cancel_period_end_description')"
    model="cancelReason"
    confirm="cancelAtPeriodEnd"
    :confirm-label="__('subscriptions.actions.cancel_period_end')"
    variant="default"
    cancel="$wire.set('targetSubscriptionId', null)"
    :placeholder="__('subscriptions.placeholders.reason_optional')"
/>
