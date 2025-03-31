<?php

require_once __DIR__.'/../src/Contracts/Browser.php';
require_once __DIR__.'./../vendor/react/event-loop/src/LoopInterface.php';
require_once __DIR__.'/LoopFake.php';
require_once __DIR__.'/BrowserFake.php';
require_once __DIR__.'/Response.php';

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

/** @var array{listenOn: string, viaPhar: bool, browser: \Tests\BrowserFake|null, loop: \Tests\LoopFake|null }  $payload */
[
    'listenOn' => $listenOn,
    'viaPhar' => $viaPhar,
    'browser' => $browser,
    'loop' => $loop,
] = $payload;

if ($browser !== null) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use ($payloadFile, $browser, $loop) {
        file_put_contents($payloadFile, serialize([
            'browser' => $browser,
            'loop' => $loop,
        ]));
    });
}

$browserFactory = $browser
    ? fn (...$args) => $browser
    : null;

if ($viaPhar) {
    call_user_func(static function () use ($listenOn, $browserFactory, $loop) { // @phpstan-ignore closure.unusedUse, closure.unusedUse, closure.unusedUse
        require __DIR__.'/../build/agent.phar';
    });
} else {
    call_user_func(static function () use ($listenOn, $browserFactory, $loop) {  // @phpstan-ignore closure.unusedUse, closure.unusedUse, closure.unusedUse
        require __DIR__.'/../src/agent.php';
    });
}

file_put_contents($payloadFile, serialize([
    'browser' => $browser,
    'loop' => $loop,
]));
