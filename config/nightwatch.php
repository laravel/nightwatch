<?php

return [
    'disabled' => env('NIGHTWATCH_DISABLED', false),
    'app_id' => '123',
    'app_secret' => 'abc',

    'deploy_id' => env('NIGHTWATCH_DEPLOY_ID'),
    'server' => gethostname(),

    'collector' => [
        'buffer_threshold' => '??', // ?
        'connection_timeout' => 0.2, // seconds
        'timeout' => 0.5, // seconds
    ],

    'agent' => [
        'address' => '127.0.0.1',
        'port' => '9247',
        'connection_limit' => 20,
        'buffer_threshold' => 10 * 1_000 * 1_000, // 5 MB
    ],

    'http' => [
        'region' => 'us-east-1',
        'timeout' => 3, // seconds
        'connection_timeout' => 1, // seconds
        'concurrent_request_limit' => 2,
    ],
];
