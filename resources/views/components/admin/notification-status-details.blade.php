{{--
    Push-delivery status details for a single Notification. Shared by the
    Notifications Index (per-row "View Status" dialog) and Form (edit mode)
    pages so both render identical content.

    Props:
      notification  App\Models\Notification
--}}
@props(['notification'])

<div class="space-y-3 text-sm">
    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('notifications.fields.status') }}</span>
        @if ($notification->push_status === \App\Enum\NotificationPushStatus::Sent)
            <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $notification->push_status->label() }}</x-ui.badge>
        @elseif ($notification->push_status === \App\Enum\NotificationPushStatus::Failed)
            <x-ui.badge variant="destructive">{{ $notification->push_status->label() }}</x-ui.badge>
        @elseif ($notification->push_status === \App\Enum\NotificationPushStatus::Pending)
            <x-ui.badge variant="default" class="border-0 bg-blue-500/15 text-blue-700 dark:text-blue-400">{{ $notification->push_status->label() }}</x-ui.badge>
        @else
            <x-ui.badge variant="outline">{{ $notification->push_status->label() }}</x-ui.badge>
        @endif
    </div>

    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('notifications.fields.sent_at') }}</span>
        <span class="font-medium">
            @if ($notification->push_sent_at)
                <x-ui.local-time :value="$notification->push_sent_at" :format="__('notifications.status_details.date_format')" />
            @else
                —
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('notifications.fields.onesignal_id') }}</span>
        <span class="max-w-[60%] truncate font-mono text-xs">{{ $notification->onesignal_notification_id ?: '—' }}</span>
    </div>

    @if ($notification->push_error)
        <div class="rounded-md border border-destructive/20 bg-destructive/5 p-3">
            <p class="mb-1 text-xs font-medium text-destructive">{{ __('notifications.fields.error') }}</p>
            <p class="text-xs text-destructive/90">{{ $notification->push_error }}</p>
        </div>
    @endif
</div>
