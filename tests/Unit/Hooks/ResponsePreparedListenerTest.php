<?php

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
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
    $listener = new ResponsePreparedListener($sensor);
    $event = new Terminating;

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
