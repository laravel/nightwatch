<?php

return [
    'enabled' => env('NIGHTWATCH_ENABLED', true),

    'env_id' => env('NIGHTWATCH_ENV_ID'),
    'env_secret' => env('NIGHTWATCH_ENV_SECRET'),

    'auth_url' => env('NIGHTWATCH_AUTH_URL', 'https://nightwatch.laravel.com/api/agent-auth'),

    'deployment' => env('NIGHTWATCH_DEPLOY'),
    'server' => env('NIGHTWATCH_SERVER', (string) gethostname()),

    'local_ingest' => env('NIGHTWATCH_LOCAL_INGEST', 'socket'), // "socket"|"log"|"null"
    'remote_ingest' => env('NIGHTWATCH_REMOTE_INGEST', 'http'),

    'buffer_threshold' => env('NIGHTWATCH_BUFFER_THRESHOLD', 1_000_000),

    'error_log_channel' => env('NIGHTWATCH_ERROR_LOG_CHANNEL', 'single'),

    'ingests' => [

        'socket' => [
            'uri' => env('NIGHTWATCH_SOCKET_INGEST_URI', '127.0.0.1:2407'),
            'connection_limit' => env('NIGHTWATCH_SOCKET_INGEST_CONNECTION_LIMIT', 20),
            'connection_timeout' => env('NIGHTWATCH_SOCKET_INGEST_CONNECTION_TIMEOUT', 0.5),
            'timeout' => env('NIGHTWATCH_SOCKET_INGEST_CONNECTION_TIMEOUT', 0.5),
        ],

        // TODO should this be "remote:http" || "local:http" etc. Will Vapor send directly via HTTP? What about local:log and remote:log?
        'http' => [
            'uri' => env('NIGHTWATCH_HTTP_INGEST_URI'),
            // TODO should remote http ingest connnection limit be configurable? Probably not.
            'connection_limit' => env('NIGHTWATCH_HTTP_INGEST_CONNECTION_LIMIT', 2),
            'connection_timeout' => env('NIGHTWATCH_HTTP_INGEST_CONNECTION_TIMEOUT', 1.0),
            'timeout' => env('NIGHTWATCH_HTTP_INGEST_TIMEOUT', 3.0),
        ],

        'log' => [
            'channel' => env('NIGHTWATCH_LOG_INGEST_CHANNEL', 'single'),
        ],

    ],
];
