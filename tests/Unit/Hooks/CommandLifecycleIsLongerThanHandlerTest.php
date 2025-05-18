<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandLifecycleIsLongerThanHandler;
use Symfony\Component\Console\Input\StringInput;

it('gracefully handles exceptions', function () {
    $ingest = fakeIngest();
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;
    $thrownInCommandSensor = false;
    nightwatch()->sensor->commandSensor = function () use (&$thrownInCommandSensor) {
        $thrownInCommandSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $handler = new CommandLifecycleIsLongerThanHandler(nightwatch());
    $handler(now(), new StringInput('app:build'), 3);

    $this->assertTrue($thrownInStageSensor);
    $this->assertTrue($thrownInCommandSensor);
    $this->assertSame(2, nightwatch()->executionState->exceptions);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        $this->assertCount(2, $records);
        $this->assertSame('exception', $records[0]['t']);
        $this->assertSame('exception', $records[1]['t']);

        return true;
    });
});
