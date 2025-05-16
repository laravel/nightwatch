<?php

use Illuminate\Console\Events\CommandFinished;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandFinishedListener;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

it('gracefully handles exceptions', function () {
    Compatibility::$terminatingEventExists = false;
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;
    nightwatch()->executionState->name = 'app:build';

    $event = new CommandFinished(
        'app:build', new StringInput('app:build'), new NullOutput, 1
    );

    $listener = new CommandFinishedListener(nightwatch());
    $listener($event);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});
