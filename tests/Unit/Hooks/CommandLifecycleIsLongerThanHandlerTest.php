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
    nightwatch()->state->stage = ExecutionStage::Bootstrap;
    $thrownInCommandSensor = false;
    nightwatch()->sensor->commandSensor = function () use (&$thrownInCommandSensor) {
        $thrownInCommandSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $handler = new CommandLifecycleIsLongerThanHandler(nightwatch());
    $handler(now(), new StringInput('app:build'), 3);

    expect($thrownInStageSensor)->toBeTrue();
    expect($thrownInCommandSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(2);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        expect($records)->toHaveCount(2);
        expect($records[0]['t'])->toBe('exception');
        expect($records[1]['t'])->toBe('exception');

        return true;
    });
});
