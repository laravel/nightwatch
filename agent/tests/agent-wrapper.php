<?php

require_once __DIR__.'./../src/Contracts/Browser.php';
require_once __DIR__.'./../vendor/react/event-loop/src/LoopInterface.php';
require_once __DIR__.'./../vendor/evenement/evenement/src/EventEmitterInterface.php';
require_once __DIR__.'./../vendor/evenement/evenement/src/EventEmitterTrait.php';
require_once __DIR__.'./../vendor/evenement/evenement/src/EventEmitter.php';
require_once __DIR__.'./../vendor/react/stream/src/ReadableStreamInterface.php';
require_once __DIR__.'./../vendor/react/stream/src/WritableStreamInterface.php';
require_once __DIR__.'./../vendor/react/stream/src/DuplexStreamInterface.php';
require_once __DIR__.'./../vendor/react/socket/src/ConnectionInterface.php';
require_once __DIR__.'./../vendor/react/socket/src/ServerInterface.php';
require_once __DIR__.'/LoopFake.php';
require_once __DIR__.'/BrowserFake.php';
require_once __DIR__.'/Response.php';
require_once __DIR__.'/PendingConnection.php';
require_once __DIR__.'/TcpServerFake.php';
require_once __DIR__.'/TcpServerFake.php';

$payloadFile = __DIR__.'/test-payload';
$payload = @file_get_contents($payloadFile);

if ($payload === false) {
    echo 'Unable to read payload file.';
    exit(1);
}

$payload = unserialize($payload);

if (! is_array($payload)) {
    echo 'Unexpected type in payload';
    exit(1);
}

/** @var array{listenOn: string, viaPhar: bool, ingestDetailsBrowser: \Tests\BrowserFake|null, ingestBrowser: \Tests\BrowserFake|null, loop: \Tests\LoopFake|null, server: \Tests\TcpServerFake|null }  $payload */
[
    'listenOn' => $listenOn,
    'viaPhar' => $viaPhar,
    'ingestDetailsBrowser' => $ingestDetailsBrowser,
    'ingestBrowser' => $ingestBrowser,
    'loop' => $loop,
    'server' => $server,
] = $payload;

$browserFactory = null;
$serverResolver = null;

if ($viaPhar === false) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use ($payloadFile, $ingestDetailsBrowser, $ingestBrowser, $loop, $server) {
        $server?->removeAllListeners();

        file_put_contents($payloadFile, serialize([
            'ingestDetailsBrowser' => $ingestDetailsBrowser,
            'ingestBrowser' => $ingestBrowser,
            'loop' => $loop,
            'server' => $server,
        ]));
    });

    $browsers = [
        $ingestDetailsBrowser ?? new Tests\BrowserFake,
        $ingestBrowser ?? new Tests\BrowserFake,
    ];

    $browserFactory = function (
        float $connectionTimeout,
        float $timeout,
        array $headers = [],
        ?string $baseUrl = null,
    ) use (&$browsers) {
        /** @var array<string, string> $headers */
        $browser = array_shift($browsers);

        if ($browser === null) {
            return null;
        }

        $browser->connectionTimeout = $connectionTimeout;
        $browser->timeout = $timeout;
        $browser->headers = $headers;
        $browser->baseUrl = $baseUrl;

        return $browser;
    };

    $serverResolver = $server
        ? fn () => $server
        : null;
}

if ($viaPhar) {
    call_user_func(static function () use ($listenOn, $browserFactory, $serverResolver, $loop) { // @phpstan-ignore closure.unusedUse, closure.unusedUse, closure.unusedUse, closure.unusedUse
        require __DIR__.'/../build/agent.phar';
    });
} else {
    call_user_func(static function () use ($listenOn, $browserFactory, $serverResolver, $loop) {  // @phpstan-ignore closure.unusedUse, closure.unusedUse, closure.unusedUse, closure.unusedUse
        require __DIR__.'/../src/agent.php';
    });
}

$server?->removeAllListeners();

file_put_contents($payloadFile, serialize([
    'ingestDetailsBrowser' => $ingestDetailsBrowser,
    'ingestBrowser' => $ingestBrowser,
    'loop' => $loop,
    'server' => $server,
]));
