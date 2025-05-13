<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestHandledListener;

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Bootstrap;

    $event = new RequestHandled(Request::create('/tests'), response(''));

    $listener = new RequestHandledListener(nightwatch());
    $listener($event);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
