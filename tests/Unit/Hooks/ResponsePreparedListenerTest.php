<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Env;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '1');
});

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
    $state = app(RequestState::class);
    $state->stage = ExecutionStage::Render;
    $listener = new ResponsePreparedListener($sensor, $state);
    $event = new ResponsePrepared(Request::create('/tests'), response(''));

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
