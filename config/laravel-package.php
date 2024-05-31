<?php

return [
    'app_id' => '123',

    'collector' => [
        'buffer_threshold' => '??', // ?
        'connection_timeout' => '??', // seconds
        'timeout' => '??', // seconds
    ],

    'agent' => [
        'buffer_threshold' => 2 * 1_000 * 1_000, // 2 MB
        'concurrent_request_limit' => 2,
        'server' => [
            'address' => '127.0.0.1',
            'port' => '8080',
            'connection_limit' => 20,
            // local tcp connections...
            // these can be reused for the collector
            'connection_timeout' => 1, // seconds
            'timeout' => 3, // seconds
        ],
        // http requests...
        'connection_timeout' => 1, // seconds
        'timeout' => 3, // seconds
    ],
];
