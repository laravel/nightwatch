<?php

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\JobAttemptListener;
use Tests\FakeJob;

it('gracefully handles exceptions', function () {
    fakeIngest();

    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });

    $thrownInJobAttemptSensor = false;
    nightwatch()->sensor->jobAttemptSensor = function () use (&$thrownInJobAttemptSensor) {
        $thrownInJobAttemptSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $thrownInExceptionSensor = false;

    $event = new JobProcessed('redis', new FakeJob);
    $handler = new JobAttemptListener(nightwatch());
    $handler($event);

    expect($thrownInJobAttemptSensor)->toBeTrue();
    expect($thrownInExceptionSensor)->toBeFalse();
    expect($unrecoverableExceptions)->toHaveCount(0);
    expect(nightwatch()->state->exceptions)->toBe(1);

    $thrownInJobAttemptSensor = false;
    $thrownInExceptionSensor = false;
    nightwatch()->sensor->jobAttemptSensor = fn () => null;
    nightwatch()->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor) {
        $thrownInExceptionSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new JobFailed('redis', new FakeJob, new RuntimeException('Whoops!'));
    $handler($event);

    expect($thrownInJobAttemptSensor)->toBeFalse();
    expect($thrownInExceptionSensor)->toBeTrue();
    expect($unrecoverableExceptions)->toHaveCount(1);
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
