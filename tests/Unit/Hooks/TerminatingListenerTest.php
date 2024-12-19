<?php

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\TerminatingListener;
use Laravel\Nightwatch\SensorManager;

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
    $listener = new TerminatingListener($nightwatch);
    $event = new Terminating;

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
