<?php

declare(strict_types=1);

return [

    'erp' => [
        'base_url'        => env('ERP_BASE_URL', 'https://erp.example.com'),
        'api_key'         => env('ERP_API_KEY', ''),
        'timeout_seconds' => (int) env('ERP_TIMEOUT_SECONDS', 15),
    ],

    'queues' => [
        'reconciliation' => env('QUEUE_RECONCILIATION', 'reconciliation'),
    ],

    'idempotency' => [
        'lock_ttl_seconds'   => (int) env('IDEMPOTENCY_LOCK_TTL', 120),
        'record_ttl_days'    => (int) env('IDEMPOTENCY_RECORD_TTL_DAYS', 7),
    ],

];
