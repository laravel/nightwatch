<?php

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\TerminatingListener;

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;

    $event = new Terminating;

    $listener = new TerminatingListener(nightwatch());
    $listener($event);

    $this->assertTrue($thrownInStageSensor);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
})->skip(fn () => ! Compatibility::$terminatingEventExists, 'Requires a more recent framework version');
