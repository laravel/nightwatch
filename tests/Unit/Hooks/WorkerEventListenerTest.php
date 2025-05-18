<?php

use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\WorkerEventListener;
use Laravel\Nightwatch\RecordsBuffer;
use Tests\FakeJob;

it('gracefully handles exceptions for JobPopping event', function () {
    nightwatch()->ingest->buffer = $buffer = new class extends RecordsBuffer
    {
        public bool $thrownInFlush = false;

        public function flush(): void
        {
            $this->thrownInFlush = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $event = new JobPopping('redis');

    $listener = new WorkerEventListener(nightwatch());
    $listener($event);

    $this->assertTrue($buffer->thrownInFlush);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});

it('gracefully handles exceptions for JobProcessing event', function () {
    $thrownInMicrotimeResolver = false;
    nightwatch()->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver) {
        $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver) {
            $thrownInMicrotimeResolver = true;

            throw new RuntimeException('Whoops!');
        };
    });
    $event = new JobProcessing('redis', new FakeJob);

    $listener = new WorkerEventListener(nightwatch());
    $listener($event);

    $this->assertTrue($thrownInMicrotimeResolver);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
