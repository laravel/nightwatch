<?php

return [
    'enabled' => env('NIGHTWATCH_ENABLED', true),
    'token' => env('NIGHTWATCH_TOKEN'),
    'deployment' => env('NIGHTWATCH_DEPLOY'),
    'server' => env('NIGHTWATCH_SERVER', (string) gethostname()),

    'sampling' => [
        'requests' => env('NIGHTWATCH_REQUEST_SAMPLE_RATE', 1.0),
    ],

    'ingest' => [
        'uri' => env('NIGHTWATCH_INGEST_URI', '127.0.0.1:2407'),
        'timeout' => env('NIGHTWATCH_INGEST_TIMEOUT', 0.5),
        'connection_timeout' => env('NIGHTWATCH_INGEST_CONNECTION_TIMEOUT', 0.5),
    ],
];
