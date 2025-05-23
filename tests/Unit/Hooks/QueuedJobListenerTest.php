<?php

namespace Tests\Unit\Hooks;

use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Hooks\QueuedJobListener;
use RuntimeException;
use Tests\TestCase;

class QueuedJobListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInQueuedJobSensor = false;
        $this->core->sensor->queuedJobSensor = function () use (&$thrownInQueuedJobSensor): void {
            $thrownInQueuedJobSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $event = new JobQueued('redis', 'default', '1', fn () => null, '{}', 0);

        $handler = new QueuedJobListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInQueuedJobSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
