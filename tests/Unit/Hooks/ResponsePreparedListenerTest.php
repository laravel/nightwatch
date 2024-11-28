<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\ResponsePrepared;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\ExecutionState;

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
    $state->stage = ExecutionStage::Render;
    $listener = new ResponsePreparedListener($sensor, $state);
    $event = new ResponsePrepared(Request::create('/tests'), response(''));

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
