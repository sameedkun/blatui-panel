<x-mail::message>
@if ($graceEndsAt)
# We Couldn't Renew Your Subscription

We couldn't charge your {{ $storeName }} payment method for your **{{ $planName }}** subscription.

<x-mail::panel>
Your access continues until **{{ $graceEndsAt->utc()->format('F j, Y \a\t g:i A T') }}** while the payment is retried.
</x-mail::panel>

Update your payment method before then to keep your subscription without interruption.
@else
# Your Subscription Is Paused

We couldn't charge your {{ $storeName }} payment method for your **{{ $planName }}** subscription, so your access has been paused.

<x-mail::panel>
The payment will keep being retried for a while. As soon as it goes through, your subscription is restored automatically — there's nothing else you need to do.
</x-mail::panel>
@endif

@if ($manageUrl)
<x-mail::button :url="$manageUrl" color="primary">
Update Payment Method
</x-mail::button>
@endif

If you've already updated your payment details, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
