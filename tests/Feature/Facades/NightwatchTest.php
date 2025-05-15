<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;

it('resolves to bound singleton instance of the Core class', function () {
    expect(Nightwatch::getFacadeRoot())->toBeInstanceOf(Core::class);

    expect(Nightwatch::getFacadeRoot())->toBe(app(Core::class));

    Facade::clearResolvedInstances();
    expect(Nightwatch::getFacadeRoot())->toBe(app(Core::class));
});

it('silently discards unrecoverable exceptions by default', function () {
    (new ReflectionClass(Nightwatch::class))->getProperty('handleUnrecoverableExceptionsUsing')->setValue(null);
    $calls = 0;
    Log::listen(function () use (&$calls) {
        $calls++;
    });

    Nightwatch::unrecoverableExceptionOccurred(new RuntimeException('Whoops!'));

    expect($calls)->toBe(0);
});

it('can register a callback to handle unrecoverable exceptions', function () {
    $handled = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function (Throwable $e) use (&$handled) {
        $handled[] = $e;
    });

    Nightwatch::unrecoverableExceptionOccurred($first = new RuntimeException('Whoops!'));
    Nightwatch::unrecoverableExceptionOccurred($second = new RuntimeException('Whoops!'));

    expect($handled)->toBe([
        $first,
        $second,
    ]);
});

it('handles unrecoverable exceptions statelessly', function () {
    app()->forgetInstance(Core::class);
    $resolved = false;
    Nightwatch::resolved(function () use (&$resolved) {
        $resolved = true;
    });

    $handled = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function (Throwable $e) use (&$handled) {
        $handled[] = $e;
    });
    Nightwatch::unrecoverableExceptionOccurred($first = new RuntimeException('Whoops!'));

    expect($resolved)->toBeFalse();
    expect($handled)->toHaveCount(1);
    expect(app()->resolved(Core::class))->toBeFalse();
});

it('silences exceptions thrown while handling exceptions', function () {
    Nightwatch::handleUnrecoverableExceptionsUsing(function (): object {
        // Should return an object. Returning an int to cause an exception.
        return 5;
    });

    Nightwatch::unrecoverableExceptionOccurred(new RuntimeException('Whoops!'));
});
