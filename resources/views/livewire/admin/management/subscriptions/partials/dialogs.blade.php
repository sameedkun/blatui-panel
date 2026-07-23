{{-- Single-subscription action dialogs, shared by the Subscriptions index and
     the subscription profile page — included by both, sharing their Livewire scope. --}}

<x-admin.reason-dialog
    id="cancel-immediately"
    title="Cancel Subscription Immediately"
    description="Access ends right away — the plan's features stop applying to this account as soon as you confirm."
    model="cancelReason"
    confirm="cancelImmediately"
    confirm-label="Cancel Immediately"
    variant="destructive"
    cancel="$wire.set('targetSubscriptionId', null)"
    placeholder="Reason (optional)"
/>

<x-admin.reason-dialog
    id="cancel-at-period-end"
    title="Cancel at Period End"
    description="Auto-renew turns off, but the user keeps full access until the current billing period ends."
    model="cancelReason"
    confirm="cancelAtPeriodEnd"
    confirm-label="Cancel at Period End"
    variant="default"
    cancel="$wire.set('targetSubscriptionId', null)"
    placeholder="Reason (optional)"
/>
