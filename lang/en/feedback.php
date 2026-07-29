<?php

return [
    'title' => 'Feedback',
    'singular' => 'Feedback Submission',
    'subtitle' => 'Review feedback submitted by users and visitors.',

    'fields' => [
        'from' => 'From',
        'subject' => 'Subject',
        'type' => 'Type',
        'status' => 'Status',
        'submitted' => 'Submitted',
        'provided_email' => 'Provided Email Address',
    ],

    'actions' => [
        'view' => 'View',
        'mark_read' => 'Mark Read',
        'mark_as_read' => 'Mark as Read',
        'resolve' => 'Resolve',
        'mark_resolved' => 'Mark Resolved',
        'ignore' => 'Ignore',
        'ignore_feedback' => 'Ignore Feedback',
        'reopen' => 'Reopen',
        'reopen_submission' => 'Reopen Submission',
        'save_notes' => 'Save Internal Notes',
        'view_user_profile' => 'View User Profile',
        'view_guest_profile' => 'View Guest Profile',
    ],

    'filters' => [
        'search' => 'Search subject, message, email...',
        'clear' => 'Clear filters',
    ],

    'stats' => [
        'total' => 'Total Feedback',
        'total_description' => 'All-time submissions',
        'new' => 'New',
        'new_description' => 'Awaiting review',
        'resolved' => 'Resolved',
        'resolved_description' => 'Handled submissions',
        'anonymous' => 'Anonymous',
        'anonymous_description' => 'Submitted without an account',
    ],

    'empty' => [
        'feedback' => 'No feedback found.',
        'subject' => 'No subject',
        'contact_email' => 'No contact email provided.',
    ],

    'show' => [
        'submitted' => 'Submitted',
        'submitted_format' => 'MMM D, YYYY [at] h:mm A',
        'feedback_number' => 'Feedback #:id',
        'message_title' => 'User Message',
        'message_description' => 'Full submitted feedback details.',
        'notes_title' => 'Internal Admin Notes',
        'notes_description' => 'Private team notes — never visible to the submitter.',
        'notes_placeholder' => 'Add internal notes, resolution details, or staff investigation logs...',
        'submitter_title' => 'Submitter Info',
        'submitter_description' => 'Account details and origin.',
        'anonymous_submission' => 'Anonymous Submission',
        'matching_account' => 'Matching Account Found',
        'controls_title' => 'Quick Status Controls',
        'controls_description' => 'Update submission state.',
    ],

    'validation' => [
        'admin_notes_invalid' => 'The internal notes must be text.',
        'admin_notes_max' => 'The internal notes may not be greater than :max characters.',
    ],

    'validation_attributes' => [
        'admin_notes' => 'internal notes',
    ],

    'toasts' => [
        'marked_read' => 'Marked as read.',
        'resolved' => 'Feedback resolved.',
        'ignored' => 'Feedback ignored.',
        'reopened' => 'Feedback reopened.',
        'notes_saved' => 'Notes saved.',
    ],
];
