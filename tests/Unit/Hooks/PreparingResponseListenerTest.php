<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\PreparingResponse;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $state = app(ExecutionState::class);
    $state->stage = ExecutionStage::Action;
    $listener = new PreparingResponseListener($sensor, $state);
    $event = new PreparingResponse(Request::create('/tests'), response(''));

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
