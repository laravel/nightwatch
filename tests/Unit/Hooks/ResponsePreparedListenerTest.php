<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\ResponsePrepared;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\SensorManager;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $nightwatch->state->stage = ExecutionStage::Render;
    $listener = new ResponsePreparedListener($nightwatch);
    $event = new ResponsePrepared(Request::create('/tests'), response(''));

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
