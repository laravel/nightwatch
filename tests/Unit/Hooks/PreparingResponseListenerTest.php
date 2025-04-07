<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\PreparingResponse;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;

beforeAll(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Action;

    $event = new PreparingResponse(Request::create('/tests'), response(''));

    $listener = new PreparingResponseListener(nightwatch());
    $listener($event);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
