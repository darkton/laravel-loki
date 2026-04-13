<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Loki OTLP Endpoint
    |--------------------------------------------------------------------------
    |
    | The URL of your Grafana Loki OTLP-compatible endpoint.
    | Example: https://logs-prod-us-central1.grafana.net/otlp/v1/logs
    |
    */
    'otlp_endpoint' => env('LOKI_OTLP_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Basic auth credentials for the Loki endpoint.
    | LOKI_USERNAME: typically your Grafana Cloud user ID (numeric)
    | LOKI_API_KEY: your Grafana Cloud access policy token
    |
    */
    'username' => env('LOKI_USERNAME'),
    'api_key'  => env('LOKI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for a response from Loki.
    |
    */
    'timeout' => (int) env('LOKI_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Buffer Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of log entries to keep in the Redis buffer.
    |
    */
    'buffer_size' => (int) env('LOKI_BUFFER_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of log entries per batch dispatched to the queue.
    |
    */
    'batch_size' => (int) env('LOKI_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, internal Loki logs (loki_internal=true) are filtered out
    | from the buffer before dispatching, preventing feedback loops.
    |
    */
    'debug' => (bool) env('LOKI_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | The Redis connection name (as defined in config/database.php) to use
    | for the log buffer.
    |
    */
    'redis_connection' => env('LOKI_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Redis Buffer Key
    |--------------------------------------------------------------------------
    |
    | The Redis list key used to store buffered log entries.
    |
    */
    'redis_key' => env('LOKI_REDIS_KEY', 'loki:buffer'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and name used when dispatching
    | SendLogsToLokiJob jobs. Set `connection` to null to use the
    | application's default queue connection (compatible with Horizon).
    |
    */
    'queue' => [
        'connection' => env('LOKI_QUEUE_CONNECTION', null),
        'name'       => env('LOKI_QUEUE_NAME', 'default'),
    ],

];
