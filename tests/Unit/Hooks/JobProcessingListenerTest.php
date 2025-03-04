<?php

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobProcessingListener;

it('gracefully handles exceptions', function () {
    nightwatch()->clock = tap(new Clock, function ($clock) {
        $clock->microtimeResolver = fn () => throw new RuntimeException('Whoops!');
    });

    $handler = new JobProcessingListener(nightwatch());

    $handler(new JobProcessing('redis', new FakeJob));

    expect(nightwatch()->state->exceptions)->toBe(1);
})->skip();
