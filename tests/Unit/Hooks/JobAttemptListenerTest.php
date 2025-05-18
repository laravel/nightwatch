<?php

use Illuminate\Queue\Events\JobProcessed;
use Laravel\Nightwatch\Hooks\JobAttemptListener;
use Tests\FakeJob;

it('gracefully handles exceptions', function () {
    $thrownInJobAttemptSensor = false;
    nightwatch()->sensor->jobAttemptSensor = function () use (&$thrownInJobAttemptSensor) {
        $thrownInJobAttemptSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new JobProcessed('redis', new FakeJob);
    $handler = new JobAttemptListener(nightwatch());
    $handler($event);

    $this->assertTrue($thrownInJobAttemptSensor);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
