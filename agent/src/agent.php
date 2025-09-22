<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Laravel\NightwatchAgent\Contracts\Browser;
use Laravel\NightwatchAgent\Factories\BrowserFactory;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use React\Stream\WritableResourceStream;

use function date;
use function file_get_contents;
use function gethostname;
use function hash;
use function in_array;
use function round;
use function rtrim;
use function str_replace;
use function strtolower;
use function substr;

require __DIR__.'/bootstrap.php';

/*
 * Testing...
 */

/** @var (Closure(float $connectionTimeout, float $timeout, array<string, string> $headers, ?string $baseUrl): Browser)|null $browserFactory */
$browserFactory ??= null;
/** @var (Closure(): ServerInterface)|null $serverResolver */
$serverResolver ??= null;
/** @var ?LoopInterface $loop */
$loop ??= null;

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
$listenOn ??= $_SERVER['NIGHTWATCH_INGEST_URI'] ?? '127.0.0.1:2407';
/** @var string $listenOn */
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
/** @var ?bool $silent */
$silent ??= strtolower($_SERVER['NIGHTWATCH_AGENT_LOG_LEVEL'] ?? '') === 'critical'; // @phpstan-ignore argument.type
/** @var ?bool $quiet */
$quiet ??= strtolower($_SERVER['NIGHTWATCH_AGENT_LOG_LEVEL'] ?? '') === 'error'; // @phpstan-ignore argument.type

/*
 * Prepare loop...
 */

$loop ??= new StreamSelectLoop;
Loop::set($loop);

/*
 * Logging helpers...
 */
$stdOut = new WritableResourceStream(STDOUT, $loop);
$stdErr = new WritableResourceStream(STDERR, $loop);

$info = static function (string $message) use ($silent, $quiet, $stdOut): void {
    if (! $quiet && ! $silent) {
        $stdOut->write(date('Y-m-d H:i:s').' [INFO] '.$message.PHP_EOL);
    }
};
$error = static function (string $message) use ($silent, $stdErr): void {
    if (! $silent) {
        $stdErr->write(date('Y-m-d H:i:s').' [ERROR] '.$message.PHP_EOL);
    }
};

/*
 * Internal state...
 */

$debug = in_array($_SERVER['NIGHTWATCH_DEBUG'] ?? null, ['true', '1'], true);
/** @var ?string $basePath */
$basePath ??= str_replace(['phar://', '/agent.phar/src'], '', __DIR__);

$signaturePath = $basePath.'/signature.txt';
$expectedSignature = file_get_contents($signaturePath);

if ($expectedSignature === false) {
    $error("Unable to read the agent's signature");

    return;
}

$tokenHash = substr(hash('xxh128', $refreshToken), 0, 7);

/*
 * Initialize services...
 */

$packageVersion = new PackageVersionRepository(
    path: $basePath.'/../../version.txt',
);

$browserFactory ??= new BrowserFactory($packageVersion);

$ingestDetailsBrowser = $browserFactory(
    connectionTimeout: $authenticationConnectionTimeout,
    timeout: $authenticationTimeout,
    headers: [
        'accept' => 'application/json',
        'authorization' => "Bearer {$refreshToken}",
        'content-type' => 'application/json',
        ...($debug ? ['nightwatch-debug' => '1'] : []),
        'nightwatch-server' => $server,
    ],
    baseUrl: rtrim($baseUrl, '/'),
);

$ingestDetails = new IngestDetailsRepository(
    loop: $loop,
    browser: $ingestDetailsBrowser,
    onAuthenticationSuccess: static fn (IngestDetails $ingestDetails, float $duration) => $info('Authentication successful ['.round($duration, 3).'s]'),
    onAuthenticationError: static fn (string $message, float $duration) => $error('Authentication failed ['.round($duration, 3).'s]: '.$message),
    onUnderQuota: static function () use (&$ingest) {
        /** @var Ingest $ingest */
        $ingest->resumeIngestion();
    },
);

$ingestBrowser = $browserFactory(
    connectionTimeout: $ingestConnectionTimeout,
    timeout: $ingestTimeout,
    headers: [
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        ...($debug ? ['nightwatch-debug' => '1'] : []),
        'nightwatch-server' => $server,
    ],
);

$ingest = new Ingest(
    loop: $loop,
    browser: $ingestBrowser,
    ingestDetails: $ingestDetails,
    buffer: new StreamBuffer(6_000_000),
    concurrentRequestLimit: 2,
    maxBufferDurationInSeconds: $debug ? 1 : 10,
    onIngestSuccess: static fn (ResponseInterface $response, float $duration) => $info('Ingest successful ['.round($duration, 3).'s]'),
    onIngestError: static fn (string $message, float $duration) => $error('Ingest failed ['.round($duration, 3).'s]: '.$message),
    onOverQuota: static fn (string $message, float $duration) => $error('Ingest attempted ['.round($duration, 3).'s]: '.$message),
);

$server = new Server(
    serverResolver: $serverResolver ?? static fn (): ServerInterface => new TcpServer($listenOn),
    tokenHash: $tokenHash,
    onServerStarted: static fn () => $info("Nightwatch agent initiated: Listening on [{$listenOn}]"),
    onServerError: static fn (string $message) => $error("Server error: {$message}"),
    onConnectionError: static fn (string $message) => $error("Connection error: {$message}"),
    onPayloadReceived: $ingest->write(...),
    onInvalidPayloadVersion: static function () use ($info, $loop, $ingest) {
        $info('Incoming payload version has changed');

        $ingest->forceDigest()->finally(static function () use ($info, $loop) {
            $loop->stop();

            $info('Shutting down');
        });
    },
    onInvalidTokenHash: static fn () => $error('Incoming token hash mismatch! Check your application/agent configuration.'),
);

$checkSignature = new CheckSignature(
    loop: $loop,
    signaturePath: $signaturePath,
    expectedSignature: $expectedSignature,
    shutdownDelayInMinutes: 5,
    onShutdownInitiated: static function ($shuttingDownIn) use ($info) {
        $info('Agent signature changed: shutting down in '.$shuttingDownIn.' minutes');
    },
    onShutdown: static function () use ($info, $loop, $ingest) {
        $ingest->forceDigest()->finally(static function () use ($info, $loop) {
            $loop->stop();

            $info('Shutting down');
        });
    },
);

/*
 * Get things rolling...
 */

$server->start();

$ingestDetails->hydrate();

$checkSignature->start();

$loop->run();
