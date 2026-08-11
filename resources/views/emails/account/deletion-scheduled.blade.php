<x-mail::message>
# Account Deletion Scheduled

@if ($initiatedByAdmin)
Your account has been scheduled for deletion on **{{ $purgesAt->utc()->format('F j, Y \a\t g:i A T') }}**.

This deletion cannot be cancelled from your account. If you believe this was done in error, please contact support.
@else
Your account is scheduled for deletion on **{{ $purgesAt->utc()->format('F j, Y \a\t g:i A T') }}**.

If you changed your mind, you can cancel the deletion from your account.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
