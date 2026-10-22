<?php

return [

    'webhook_token' => env('WEBHOOK_TOKEN', ''),

    'mock_scenario' => env('MOCK_SCENARIO', 'success'),

    'max_attempts' => 3,

    'backoff_ms' => array_map(
        'intval',
        explode(',', (string) env('SYNC_RETRY_BACKOFF_MS', '200,500,1000')),
    ),

    // Upper bound for a Retry-After pause so one response cannot stall the worker.
    'retry_after_cap_seconds' => (int) env('SYNC_RETRY_AFTER_CAP_SECONDS', 5),

    'dispatch_grace_seconds' => (int) env('SYNC_DISPATCH_GRACE_SECONDS', 30),

    'webhook_lease_seconds' => 30,

    'refresh_lease_seconds' => 60,

    'external_crm' => [
        'transport' => env('EXTERNAL_CRM_TRANSPORT', 'http'),
        'base_url' => env('EXTERNAL_CRM_BASE_URL', 'http://app:8000'),
        'connect_timeout' => (float) env('EXTERNAL_CRM_CONNECT_TIMEOUT', 2),
        'timeout' => (float) env('EXTERNAL_CRM_TIMEOUT', 5),
    ],

];
