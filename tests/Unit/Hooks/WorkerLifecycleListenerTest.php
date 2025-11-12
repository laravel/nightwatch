<?php

namespace Tests\Unit\Hooks;

use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\WorkerLifecycleListener;
use Laravel\Nightwatch\RecordsBuffer;
use RuntimeException;
use Tests\FakeJob;
use Tests\TestCase;

use function tap;

class WorkerLifecycleListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions_for_job_popping_event(): void
    {
        $this->core->ingest->buffer = $buffer = new class(500) extends RecordsBuffer
        {
            public bool $thrownInFlush = false;

            public function flush(): void
            {
                $this->thrownInFlush = true;

                throw new RuntimeException('Whoops!');
            }
        };
        $event = new JobPopping('redis');

        $listener = new WorkerLifecycleListener($this->core);
        $listener($event);

        $this->assertTrue($buffer->thrownInFlush);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }

    public function test_it_gracefully_handles_exceptions_for_job_processing_event(): void
    {
        $thrownInMicrotimeResolver = false;
        $this->core->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver): void {
            $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver): void {
                $thrownInMicrotimeResolver = true;

                throw new RuntimeException('Whoops!');
            };
        });
        $event = new JobProcessing('redis', new FakeJob);

        $listener = new WorkerLifecycleListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInMicrotimeResolver);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
