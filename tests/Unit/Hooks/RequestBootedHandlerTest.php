<?php

namespace Tests\Unit\Hooks;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestBootedHandler;
use RuntimeException;
use Tests\TestCase;

class RequestBootedHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $handler = new RequestBootedHandler($this->core);
        $handler($this->app);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
