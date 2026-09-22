<?php

return [
    'title' => 'Announcements',
    'singular' => 'Announcement',
    'subtitle' => 'Broadcast push announcements to every subscribed device via OneSignal.',

    'actions' => [
        'create' => 'Create Announcement',
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
        'announcement' => 'Announcement',
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
        'total' => 'Total Announcements',
        'total_description' => 'All-time broadcasts',
        'sent' => 'Sent',
        'sent_description' => 'Delivered to OneSignal',
        'failed' => 'Failed',
        'failed_description' => 'Need a retry',
        'drafts' => 'Drafts',
        'drafts_description' => 'Not yet sent',
    ],

    'empty' => 'No announcements found.',

    'form' => [
        'create_title' => 'Create Announcement',
        'edit_title' => 'Edit Announcement',
        'create_description' => 'Compose a push announcement broadcast to every subscribed device.',
        'edit_description' => 'Update the announcement content below.',
        'breadcrumb_create' => 'Create',
        'breadcrumb_edit' => 'Edit',
        'title_placeholder' => 'e.g. New feature available!',
        'message_placeholder' => 'Write the announcement body shown to recipients...',
        'link_description' => 'Opened when the announcement is tapped (optional).',
        'send_now' => 'Send push announcement now',
        'send_now_description' => 'Leave unchecked to save this as a draft you can send later.',
        'resend_after_update' => 'Resend push announcement after saving',
        'resend_after_update_description' => 'Broadcasts the updated content to every device again. Leave unchecked to only update the record.',
        'saving' => 'Saving...',
        'creating' => 'Creating...',
    ],

    'dialogs' => [
        'delete_title' => 'Delete Announcement',
        'delete_description' => 'This will permanently delete the announcement. This action cannot be undone.',
        'bulk_delete_title' => 'Delete :count Announcements',
        'bulk_delete_description' => 'This permanently deletes all selected announcements. This action cannot be undone.',
        'status_title' => 'Push Announcement Status',
        'watching' => 'Watching for a status update...',
    ],

    'status_details' => [
        'date_format' => 'MMM D, YYYY [at] h:mm A',
    ],

    'validation' => [
        'title_required' => 'Enter an announcement title.',
        'title_invalid' => 'The announcement title must be text.',
        'title_max' => 'The announcement title may not be greater than :max characters.',
        'message_required' => 'Enter an announcement message.',
        'message_invalid' => 'The announcement message must be text.',
        'message_max' => 'The announcement message may not be greater than :max characters.',
        'type_required' => 'Select an announcement type.',
        'type_invalid' => 'Select a valid announcement type.',
        'link_url' => 'Enter a valid announcement link.',
        'link_max' => 'The announcement link may not be greater than :max characters.',
    ],

    'validation_attributes' => [
        'title' => 'announcement title',
        'message' => 'announcement message',
        'type' => 'announcement type',
        'link' => 'announcement link',
    ],

    'toasts' => [
        'deleted' => ':title deleted.',
        'bulk_deleted' => ':count announcements deleted.',
        'push_queued' => 'Push queued for “:title”.',
        'updated_resend' => 'Announcement updated — push resend queued.',
        'updated' => 'Announcement updated.',
        'created_sent' => 'Announcement created — push queued.',
        'created_draft' => 'Announcement created as a draft.',
    ],
];
