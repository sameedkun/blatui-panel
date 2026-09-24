<?php

/*
|--------------------------------------------------------------------------
| API Request Logging
|--------------------------------------------------------------------------
|
| Every request targeting the API surface (see App\Support\ApiRequest) gets
| a request id + correlation id, and is recorded after its response has been
| sent (terminable middleware) into a buffer, flushed in batches to the
| api_request_* tables, rolled up into api_request_stats, and pruned per the
| retention tiers below. See PROJECT_CONTEXT.md "API request logging".
|
*/

return [

    'enabled' => env('API_LOG_ENABLED', true),

    /*
    | 'redis' — one RPUSH per request, bulk-inserted by FlushApiRequestLogs
    |           every minute. The production default: no DB write ever
    |           happens inside a request's lifecycle (so a request becomes
    |           searchable in the panel up to ~a minute after it's served).
    | 'sync'  — inserts directly from terminate(). Used by the test suite and
    |           handy for local debugging without a scheduler running.
    */
    'buffer' => env('API_LOG_BUFFER', 'redis'),

    'redis' => [
        'connection' => env('API_LOG_REDIS_CONNECTION', 'default'),
        'key' => 'api_logs:buffer',
    ],

    'flush' => [
        'batch_size' => 1000,
        // Upper bound on batches per flush run, so one run can't hold a queue
        // worker indefinitely while traffic keeps refilling the buffer.
        'max_batches' => 50,
    ],

    /*
    | Percentage (1-100) of requests kept per status class. Each class falls
    | back to the global rate when its own env var is unset. Requests with an
    | exception, an error code, or slower than slow_request_ms are always kept
    | regardless. Kept rows carry a sample_weight (100 / rate) so aggregated
    | request counts stay accurate even when sampling is on.
    */
    'sampling' => [
        'rate' => (int) env('API_LOG_SAMPLE_RATE', 100),
        'rates' => [
            2 => env('API_LOG_SAMPLE_RATE_2XX'),
            3 => env('API_LOG_SAMPLE_RATE_3XX'),
            4 => env('API_LOG_SAMPLE_RATE_4XX'),
            5 => env('API_LOG_SAMPLE_RATE_5XX'),
        ],
    ],

    'slow_request_ms' => (int) env('API_LOG_SLOW_REQUEST_MS', 1000),

    'slow_query_ms' => (int) env('API_LOG_SLOW_QUERY_MS', 100),

    'max_slow_queries' => 10,

    'max_trace_frames' => 30,

    /*
    | Request/response bodies larger than this (after sanitizing, JSON-encoded)
    | are stored truncated, with a *_truncated flag set on the payload row.
    */
    'max_body_kb' => (int) env('API_LOG_MAX_BODY_KB', 32),

    /*
    | A payload row (headers + bodies) is only written when it's worth keeping:
    | the request carried a body or query string, failed (status >= 400), ran a
    | slow query, or wasn't a read (GET/HEAD). Set successful_reads to true to
    | capture every request's payload, or list route URIs (Str::is patterns,
    | e.g. 'api/v1/subscription*') under 'always'.
    */
    'capture' => [
        'successful_reads' => (bool) env('API_LOG_CAPTURE_SUCCESSFUL_READS', false),
        'always' => [],
    ],

    /*
    | Str::is patterns (matched against the request path, no leading slash)
    | that are never logged at all.
    */
    'exclude_paths' => [],

    'sanitizer' => [
        // Headers replaced wholesale. Authorization is special-cased to keep
        // the Sanctum token id ("42|[REDACTED]") — it identifies which token
        // made the call without exposing the secret.
        'redact_headers' => [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-csrf-token',
            'x-xsrf-token',
            'php-auth-pw',
        ],

        // Keys are normalized (lowercased, non-alphanumerics stripped) before
        // matching: a key containing any of these is fully redacted.
        'redact_keys_containing' => [
            'password',
            'passwd',
            'secret',
            'token',
            'apikey',
            'authorization',
            'signature',
            'cvv',
            'cvc',
            'cardnumber',
            'privatekey',
            'credential',
            'fingerprint',
        ],

        // Exact (normalized) key matches that are fully redacted — too short
        // to substring-match safely ("pin" would hit "shipping").
        'redact_keys_exact' => [
            'pin',
            'otp',
            'hash',
            'ssn',
            'iban',
        ],

        // Exact (normalized) key → partial-mask strategy for personal data.
        'mask_keys' => [
            'email' => 'email',
            'phone' => 'phone',
            'mobile' => 'phone',
            'phonenumber' => 'phone',
            'name' => 'name',
            'firstname' => 'name',
            'lastname' => 'name',
            'fullname' => 'name',
            'address' => 'name',
            'addressline1' => 'name',
            'addressline2' => 'name',
            'street' => 'name',
            'city' => 'name',
            'postalcode' => 'name',
            'zip' => 'name',
            'dob' => 'date',
            'dateofbirth' => 'date',
            'birthdate' => 'date',
            'birthday' => 'date',
        ],
    ],

    /*
    | Retention tiers, enforced daily by PruneApiRequestLogs. Monthly stats are
    | kept forever. Raw rows are additionally never pruned past the last hour
    | AggregateApiRequestStats has rolled up, so a stalled aggregator can
    | never cause data loss.
    */
    'retention' => [
        'raw_days' => (int) env('API_LOG_RETENTION_RAW_DAYS', 7),
        'exception_days' => (int) env('API_LOG_RETENTION_EXCEPTION_DAYS', 30),
        'hourly_days' => (int) env('API_LOG_RETENTION_HOURLY_DAYS', 90),
        'daily_days' => (int) env('API_LOG_RETENTION_DAILY_DAYS', 365),
    ],

];
