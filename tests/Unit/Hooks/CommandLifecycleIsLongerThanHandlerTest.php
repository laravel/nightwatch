<?php

namespace Tests\Unit\Hooks;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandLifecycleIsLongerThanHandler;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Tests\TestCase;

use function now;

class CommandLifecycleIsLongerThanHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;
        $thrownInCommandSensor = false;
        $this->core->sensor->commandSensor = function () use (&$thrownInCommandSensor): void {
            $thrownInCommandSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $handler = new CommandLifecycleIsLongerThanHandler($this->core);
        $handler(now(), new StringInput('app:build'), 3);

        $this->assertTrue($thrownInStageSensor);
        $this->assertTrue($thrownInCommandSensor);
        $this->assertSame(2, $this->core->executionState->exceptions);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);
            $this->assertSame('exception', $records[0]['t']);
            $this->assertSame('exception', $records[1]['t']);

            return true;
        });
    }
}
