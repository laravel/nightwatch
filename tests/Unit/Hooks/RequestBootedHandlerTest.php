<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestBootedHandler;

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;

    $handler = new RequestBootedHandler(nightwatch());
    $handler(app());

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});
