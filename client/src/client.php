<?php

namespace Laravel\NightwatchClient;

require __DIR__.'/../vendor/react/async/src/functions_include.php';
require __DIR__.'/../vendor/react/promise/src/functions_include.php';
require __DIR__.'/../vendor/autoload.php';

$ingestFactory = static function (
    ?string $transmitTo = null,
    ?float $ingestTimeout = null,
    ?float $ingestConnectionTimeout = null,
): Ingest {
    return (new IngestFactory)(
        transmitTo: $transmitTo ?? '127.0.0.1:2407',
        ingestTimeout: $ingestTimeout ?? 0.5,
        ingestConnectionTimeout: $ingestConnectionTimeout ?? 0.5,
    );
};
