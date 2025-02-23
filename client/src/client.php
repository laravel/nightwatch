<?php

namespace Laravel\NightwatchClient;

require __DIR__.'/../vendor/autoload.php';

/*
 * Input...
 */

/** @var ?string $payload */
$payload ??= '';
/** @var ?string $transmitTo */
$transmitTo ??= '127.0.0.1:2407';
/** @var ?float $ingestTimeout */
$ingestTimeout ??= 0.5;
/** @var ?float $ingestConnectionTimeout */
$ingestConnectionTimeout ??= 0.5;

/*
 * Initialize services...
 */

$ingest = (new IngestFactory)(
    transmitTo: $transmitTo,
    ingestTimeout: $ingestTimeout,
    ingestConnectionTimeout: $ingestConnectionTimeout,
);

/*
 * Get things rolling...
 */

$ingest->write($payload);
