<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandFinishedListener;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class CommandFinishedListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        Compatibility::$terminatingEventExists = false;
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;
        $this->core->executionState->name = 'app:build';

        $event = new CommandFinished(
            'app:build', new StringInput('app:build'), new NullOutput, 1
        );

        $listener = new CommandFinishedListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
