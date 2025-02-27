<?php

use Laravel\NightwatchAgent\Signature;

it('throws an exception if the signature file does not exist', function () {
    $signature = new Signature(
        path: __DIR__.'/file-that-does-not-exist',
        verificationIntervalInSeconds: 1,
        onChange: fn () => null,
    );

    $signature->capture();
})->throws(RuntimeException::class);
