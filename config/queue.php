<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | In production, Redis is recommended for better performance and scalability.
    | For local development, you might want to use 'sync' or 'database'.
    |
    */
    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Production-tuned queue configurations with proper timeouts and settings.
    |
    */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'mysql')),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 120),
            'after_commit' => env('QUEUE_AFTER_COMMIT', false),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => env('REDIS_QUEUE_BLOCK_FOR', 5), // 5 seconds blocking pop
            'after_commit' => env('QUEUE_AFTER_COMMIT', false),
            'backoff' => [
                'default' => env('REDIS_QUEUE_BACKOFF', 60), // Default retry delay
            ],
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => env('QUEUE_AFTER_COMMIT', false),
            'visibility_timeout' => (int) env('SQS_VISIBILITY_TIMEOUT', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | Configured to use the same database as the main application.
    |
    */
    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | Using database-uuids for better failed job tracking in production.
    |
    */
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Production Settings
    |--------------------------------------------------------------------------
    |
    | These are not part of Laravel's default config but can be added for
    | enhanced queue management.
    |
    */
    'production' => [
        'max_attempts' => (int) env('QUEUE_MAX_ATTEMPTS', 5),
        'timeout' => (int) env('QUEUE_TIMEOUT', 60), // Default job timeout in seconds
        'sleep' => (int) env('QUEUE_SLEEP', 3), // Sleep time when no jobs available
        'max_processes' => (int) env('QUEUE_MAX_PROCESSES', 8), // For supervisor
        'balance' => env('QUEUE_BALANCE', 'auto'), // Can be 'auto', 'simple', 'null'
    ],
];