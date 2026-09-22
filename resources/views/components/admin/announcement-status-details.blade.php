{{--
    Push-delivery status details for a single Announcement. Shared by the
    Announcements Index (per-row "View Status" dialog) and Form (edit mode)
    pages so both render identical content.

    Props:
      announcement  App\Models\Announcement
--}}
@props(['announcement'])

<div class="space-y-3 text-sm">
    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('announcements.fields.status') }}</span>
        @if ($announcement->push_status === \App\Enum\AnnouncementPushStatus::Sent)
            <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $announcement->push_status->label() }}</x-ui.badge>
        @elseif ($announcement->push_status === \App\Enum\AnnouncementPushStatus::Failed)
            <x-ui.badge variant="destructive">{{ $announcement->push_status->label() }}</x-ui.badge>
        @elseif ($announcement->push_status === \App\Enum\AnnouncementPushStatus::Pending)
            <x-ui.badge variant="default" class="border-0 bg-blue-500/15 text-blue-700 dark:text-blue-400">{{ $announcement->push_status->label() }}</x-ui.badge>
        @else
            <x-ui.badge variant="outline">{{ $announcement->push_status->label() }}</x-ui.badge>
        @endif
    </div>

    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('announcements.fields.sent_at') }}</span>
        <span class="font-medium">
            @if ($announcement->push_sent_at)
                <x-ui.local-time :value="$announcement->push_sent_at" :format="__('announcements.status_details.date_format')" />
            @else
                —
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-muted-foreground">{{ __('announcements.fields.onesignal_id') }}</span>
        <span class="max-w-[60%] truncate font-mono text-xs">{{ $announcement->onesignal_notification_id ?: '—' }}</span>
    </div>

    @if ($announcement->push_error)
        <div class="rounded-md border border-destructive/20 bg-destructive/5 p-3">
            <p class="mb-1 text-xs font-medium text-destructive">{{ __('announcements.fields.error') }}</p>
            <p class="text-xs text-destructive/90">{{ $announcement->push_error }}</p>
        </div>
    @endif
</div>
