<?php

namespace Tests\Unit\Hooks;

use Illuminate\Queue\Events\JobProcessed;
use Laravel\Nightwatch\Hooks\JobAttemptListener;
use RuntimeException;
use Tests\FakeJob;
use Tests\TestCase;

class JobAttemptListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInJobAttemptSensor = false;
        $this->core->sensor->jobAttemptSensor = function () use (&$thrownInJobAttemptSensor): void {
            $thrownInJobAttemptSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $event = new JobProcessed('redis', new FakeJob);
        $handler = new JobAttemptListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInJobAttemptSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
