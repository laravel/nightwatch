<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\ResponsePrepared;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Render;

    $event = new ResponsePrepared(Request::create('/tests'), response(''));

    $listener = new ResponsePreparedListener(nightwatch());
    $listener($event);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});
