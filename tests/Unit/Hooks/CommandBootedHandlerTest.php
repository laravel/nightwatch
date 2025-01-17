<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\CommandBootedHandler;
use Laravel\Nightwatch\SensorManager;

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
    $handler = new CommandBootedHandler($nightwatch);

    $handler(app());

    expect($sensor->thrown)->toBeTrue();
});
