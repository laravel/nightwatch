<?php

namespace Laravel\NightwatchAgent;

use Laravel\NightwatchAgent\Factories\IngestDetailsRepositoryFactory;
use Laravel\NightwatchAgent\Factories\IngestFactory;
use Laravel\NightwatchAgent\Factories\ServerFactory;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use Throwable;

use function date;
use function round;
use function str_replace;

require __DIR__.'/../vendor/react/promise/src/functions_include.php';
require __DIR__.'/../vendor/autoload.php';

/*
 * Input...
 */

/** @var ?string $refreshToken */
$refreshToken ??= $_SERVER['NIGHTWATCH_TOKEN'] ?? '';
/** @var string $refreshToken */
/** @var ?string $baseUrl */
$baseUrl ??= $_SERVER['NIGHTWATCH_BASE_URL'] ?? 'https://nightwatch.laravel.com';
/** @var string $baseUrl */
/** @var ?string $listenOn */
$listenOn ??= '127.0.0.1:2407';
/** @var ?float $authenticationConnectionTimeout */
$authenticationConnectionTimeout ??= 5;
/** @var ?float $authenticationTimeout */
$authenticationTimeout ??= 10;
/** @var ?float $ingestConnectionTimeout */
$ingestConnectionTimeout ??= 5;
/** @var ?float $ingestTimeout */
$ingestTimeout ??= 10;

/*
 * Internal state...
 */

$debug = (bool) ($_SERVER['NIGHTWATCH_DEBUG'] ?? false);
$basePath = str_replace(['phar://', '/agent.phar/src'], '', __DIR__);

/*
 * Logging helpers...
 */

$info = static function (string $message): void {
    echo date('Y-m-d H:i:s').' [INFO] '.$message.PHP_EOL;
};
$error = static function (string $message): void {
    echo date('Y-m-d H:i:s').' [ERROR] '.$message.PHP_EOL;
};

/*
 * Initialize services...
 */

$packageVersion = new PackageVersionRepository(
    path: $basePath.'/../../version.txt',
);

$ingestDetails = (new IngestDetailsRepositoryFactory)(
    baseUrl: $baseUrl,
    refreshToken: $refreshToken,
    connectionTimeout: $authenticationConnectionTimeout,
    timeout: $authenticationTimeout,
    preemptivelyRefreshInSeconds: 60,
    minRefreshDurationInSeconds: 60,
    packageVersion: $packageVersion,
    onAuthenticationSuccess: static fn (IngestDetails $ingestDetails, float $duration) => $info('Authentication successful ['.round($duration, 3).'s]'),
    onAuthenticationError: static fn (Throwable $e, float $duration) => $info('Authentication failed ['.round($duration, 3).'s]: '.$e->getMessage()),
);

$ingest = (new IngestFactory)(
    ingestDetails: $ingestDetails,
    connectionTimeout: $ingestConnectionTimeout,
    timeout: $ingestTimeout,
    debug: $debug,
    threshold: 6_000_000,
    concurrentRequestLimit: 2,
    maxBufferDurationInSeconds: 10,
    packageVersion: $packageVersion,
    onIngestSuccess: static fn (ResponseInterface $response, float $duration) => $info('Ingest successful ['.round($duration, 3).'s]'),
    onIngestError: static fn (Throwable $e, float $duration) => $info('Ingest failed ['.round($duration, 3).'s]: '.$e->getMessage()),
);

$server = (new ServerFactory)(
    listenOn: $listenOn,
    onServerStarted: static fn () => $info("Nightwatch agent initiated: Listening on [{$listenOn}]."),
    onServerError: static fn (Throwable $e) => $error("Server error: {$e->getMessage()}"),
    onConnectionError: static fn (Throwable $e) => $error("Connection error: {$e->getMessage()}"),
    onPayloadReceived: $ingest->write(...),
);

/*
 * Get things rolling...
 */

$server->start();

$ingestDetails->hydrate();

Loop::run();
