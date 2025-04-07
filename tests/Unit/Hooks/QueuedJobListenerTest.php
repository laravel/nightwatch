<?php

use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Hooks\QueuedJobListener;

it('gracefully handles exceptions', function () {
    $thrownInQueuedJobSensor = false;
    nightwatch()->sensor->queuedJobSensor = function () use (&$thrownInQueuedJobSensor) {
        $thrownInQueuedJobSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $event = new JobQueued('redis', 'default', '1', fn () => null, '{}', 0);

    $handler = new QueuedJobListener(nightwatch());
    $handler($event);

    expect($thrownInQueuedJobSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
