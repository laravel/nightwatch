<?php

namespace Tests\Unit\Hooks;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandBootedHandler;
use RuntimeException;
use Tests\TestCase;

class CommandBootedHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $handler = new CommandBootedHandler($this->core);
        $handler($this->app);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
