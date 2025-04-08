<?php

namespace Laravel\NightwatchAgent;

use Laravel\NightwatchAgent\Factories\BrowserFactory;
use Laravel\NightwatchAgent\Factories\ServerFactory;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Throwable;

use function date;
use function gethostname;
use function round;
use function rtrim;
use function str_replace;

require __DIR__.'/../vendor/react/promise/src/functions_include.php';
require __DIR__.'/../vendor/autoload.php';

/*
 * Testing setup...
 */

$loop ??= null;
$browserFactory ??= null;

/**
 * @var ?BrowserFactory $browserFactory
 * @var ?LoopInterface $loop
 */

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
/** @var ?string $server */
$server ??= (string) gethostname();

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

if ($loop) {
    Loop::set($loop);
} else {
    $loop = Loop::get();
}

$packageVersion = new PackageVersionRepository(
    path: $basePath.'/../../version.txt',
);

$browserFactory ??= new BrowserFactory($packageVersion);

$ingestDetailsBrowser = $browserFactory(
    connectionTimeout: $authenticationConnectionTimeout,
    timeout: $authenticationTimeout,
    server: $server,
    headers: [
        'authorization' => "Bearer {$refreshToken}",
        'content-type' => 'application/json',
    ],
    baseUrl: rtrim($baseUrl, '/'),
);

$ingestDetails = new IngestDetailsRepository(
    loop: $loop,
    browser: $ingestDetailsBrowser,
    onAuthenticationSuccess: static fn (IngestDetails $ingestDetails, float $duration) => $info('Authentication successful ['.round($duration, 3).'s]'),
    onAuthenticationError: static fn (Throwable $e, float $duration) => $info('Authentication failed ['.round($duration, 3).'s]: '.$e->getMessage()),
);

$ingestBrowser = $browserFactory(
    connectionTimeout: $ingestConnectionTimeout,
    timeout: $ingestTimeout,
    server: $server,
    headers: [
        'content-encoding' => 'gzip',
        'content-type' => 'application/octet-stream',
    ],
    debug: $debug,
);

$ingest = new Ingest(
    loop: $loop,
    browser: $ingestBrowser,
    ingestDetails: $ingestDetails,
    buffer: new StreamBuffer(6_000_000),
    concurrentRequestLimit: 2,
    maxBufferDurationInSeconds: $debug ? 1 : 10,
    onIngestSuccess: static fn (ResponseInterface $response, float $duration) => $info('Ingest successful ['.round($duration, 3).'s]'),
    onIngestError: static fn (Throwable $e, float $duration) => $info('Ingest failed ['.round($duration, 3).'s]: '.$e->getMessage()),
);

$server = (new ServerFactory)(
    listenOn: $listenOn,
    onServerStarted: static fn () => $info("Nightwatch agent initiated: Listening on [{$listenOn}]"),
    onServerError: static fn (Throwable $e) => $error("Server error: {$e->getMessage()}"),
    onConnectionError: static fn (Throwable $e) => $error("Connection error: {$e->getMessage()}"),
    onPayloadReceived: $ingest->write(...),
);

/*
 * Get things rolling...
 */

$server->start();

$ingestDetails->hydrate();

$loop->run();
