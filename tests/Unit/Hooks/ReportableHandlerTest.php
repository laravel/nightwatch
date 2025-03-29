<?php

use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ReportableHandler;

it('gracefully handles exceptions', function () {
    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });
    $thrownInExceptionSensor = false;
    nightwatch()->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor) {
        $thrownInExceptionSensor = true;

        throw new RuntimeException('Whoops sensor!');
    };

    $exception = new RuntimeException('Whoops app!');

    $handler = new ReportableHandler(nightwatch());
    $handler($exception);

    expect($thrownInExceptionSensor)->toBeTrue();
    expect($unrecoverableExceptions)->toHaveCount(1);
    expect($unrecoverableExceptions[0]->getMessage())->toBe('Whoops sensor!');
});
