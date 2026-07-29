<?php

return [
    'title' => 'Notifications',
    'singular' => 'Notification',
    'subtitle' => 'Broadcast push notifications to every subscribed device via OneSignal.',

    'actions' => [
        'create' => 'Create Notification',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'resend' => 'Resend',
        'retry' => 'Retry',
        'view_status' => 'View Status',
        'save_changes' => 'Save Changes',
        'create_send' => 'Create & Send',
        'save_draft' => 'Save as Draft',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'clear_selection' => 'Clear selection',
        'selected' => '{1} :count selected|[2,*] :count selected',
    ],

    'fields' => [
        'notification' => 'Notification',
        'title' => 'Title',
        'message' => 'Message',
        'type' => 'Type',
        'status' => 'Status',
        'push_status' => 'Push Status',
        'link' => 'Link',
        'created' => 'Created',
        'sent_at' => 'Sent At',
        'onesignal_id' => 'OneSignal ID',
        'error' => 'Error',
    ],

    'filters' => [
        'search' => 'Search title, message...',
        'clear' => 'Clear filters',
    ],

    'stats' => [
        'total' => 'Total Notifications',
        'total_description' => 'All-time broadcasts',
        'sent' => 'Sent',
        'sent_description' => 'Delivered to OneSignal',
        'failed' => 'Failed',
        'failed_description' => 'Need a retry',
        'drafts' => 'Drafts',
        'drafts_description' => 'Not yet sent',
    ],

    'empty' => 'No notifications found.',

    'form' => [
        'create_title' => 'Create Notification',
        'edit_title' => 'Edit Notification',
        'create_description' => 'Compose a push notification broadcast to every subscribed device.',
        'edit_description' => 'Update the notification content below.',
        'breadcrumb_create' => 'Create',
        'breadcrumb_edit' => 'Edit',
        'title_placeholder' => 'e.g. New feature available!',
        'message_placeholder' => 'Write the notification body shown to recipients...',
        'link_description' => 'Opened when the notification is tapped (optional).',
        'send_now' => 'Send push notification now',
        'send_now_description' => 'Leave unchecked to save this as a draft you can send later.',
        'resend_after_update' => 'Resend push notification after saving',
        'resend_after_update_description' => 'Broadcasts the updated content to every device again. Leave unchecked to only update the record.',
        'saving' => 'Saving...',
        'creating' => 'Creating...',
    ],

    'dialogs' => [
        'delete_title' => 'Delete Notification',
        'delete_description' => 'This will permanently delete the notification. This action cannot be undone.',
        'bulk_delete_title' => 'Delete :count Notifications',
        'bulk_delete_description' => 'This permanently deletes all selected notifications. This action cannot be undone.',
        'status_title' => 'Push Notification Status',
        'watching' => 'Watching for a status update...',
    ],

    'status_details' => [
        'date_format' => 'MMM D, YYYY [at] h:mm A',
    ],

    'validation' => [
        'title_required' => 'Enter a notification title.',
        'title_invalid' => 'The notification title must be text.',
        'title_max' => 'The notification title may not be greater than :max characters.',
        'message_required' => 'Enter a notification message.',
        'message_invalid' => 'The notification message must be text.',
        'message_max' => 'The notification message may not be greater than :max characters.',
        'type_required' => 'Select a notification type.',
        'type_invalid' => 'Select a valid notification type.',
        'link_url' => 'Enter a valid notification link.',
        'link_max' => 'The notification link may not be greater than :max characters.',
    ],

    'validation_attributes' => [
        'title' => 'notification title',
        'message' => 'notification message',
        'type' => 'notification type',
        'link' => 'notification link',
    ],

    'toasts' => [
        'deleted' => ':title deleted.',
        'bulk_deleted' => ':count notifications deleted.',
        'push_queued' => 'Push queued for “:title”.',
        'updated_resend' => 'Notification updated — push resend queued.',
        'updated' => 'Notification updated.',
        'created_sent' => 'Notification created — push queued.',
        'created_draft' => 'Notification created as a draft.',
    ],
];
