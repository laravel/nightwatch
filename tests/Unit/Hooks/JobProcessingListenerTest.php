<?php

use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobProcessingListener;

it('gracefully handles exceptions', function () {
    $thrownInMicrotimeResolver = false;
    nightwatch()->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver) {
        $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver) {
            $thrownInMicrotimeResolver = true;

            throw new RuntimeException('Whoops!');
        };
    });
    $event = new JobProcessing('redis', new FakeJob);

    $handler = new JobProcessingListener(nightwatch());
    $handler($event);

    expect($thrownInMicrotimeResolver)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), 'Laravel 10 support is pending');
