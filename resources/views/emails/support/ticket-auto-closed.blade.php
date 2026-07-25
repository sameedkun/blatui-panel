<x-mail::message>
# Ticket #{{ $ticket->id }} Closed

Your support ticket, **{{ $ticket->subject }}**, has been automatically closed after
{{ $inactiveDays }} days with no reply from you following our last response.

If this doesn't resolve your issue, just reply to this ticket and it will be reopened
automatically.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
