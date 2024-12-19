<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\PreparingResponse;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;
use Laravel\Nightwatch\SensorManager;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $nightwatch->state->stage = ExecutionStage::Action;
    $listener = new PreparingResponseListener($nightwatch);
    $event = new PreparingResponse(Request::create('/tests'), response(''));

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
