<?php

return [
    'disabled' => env('NIGHTWATCH_DISABLED', false),

    'app_id' => '123',
    'app_secret' => 'abc',

    'deploy' => env('NIGHTWATCH_DEPLOY'),
    'server' => gethostname(),

    'ingest' => [
        'local' => [
            'uri' => '127.0.0.1:2357',
            'connection_limit' => 20,
            'connection_timeout' => 0.5,              // seconds
            'timeout' => 0.5,                         // seconds
            'buffer_threshold' => 1 * 1_000 * 1_000, // bytes
        ],

        'remote' => [
            'uri' => 'https://khq5ni773stuucqrxebn3a5zbi0ypexu.lambda-url.us-east-1.on.aws',
            'connection_limit' => 2,
            'timeout' => 3,                           // seconds
            'connection_timeout' => 1,                // seconds
            'buffer_threshold' => 10 * 1_000 * 1_000, // bytes
        ],
    ],
];
