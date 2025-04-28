<?php

use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\JobExceptionOccurredListener;
use Tests\FakeJob;

it('gracefully handles exceptions', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $throwInExceptionSensor = false;
    nightwatch()->sensor->exceptionSensor = function () use (&$throwInExceptionSensor) {
        $throwInExceptionSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new JobExceptionOccurred(
        'redis',
        new FakeJob,
        new RuntimeException('Whoops!')
    );

    $listener = new JobExceptionOccurredListener(nightwatch());
    $listener($event);

    expect($throwInExceptionSensor)->toBeTrue();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops!');
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), 'Laravel 10 support is pending');
