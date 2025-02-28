<?php

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\TerminatingListener;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\Support;

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
    $listener = new TerminatingListener($nightwatch);
    $event = new Terminating;

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
})->skip(fn () => ! Support::$terminatingEventExists, 'The terminating event does not exist');
