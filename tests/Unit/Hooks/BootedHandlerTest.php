<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\BootedHandler;
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
    $handler = new BootedHandler($sensor);

    $handler(app());

    expect($sensor->thrown)->toBeTrue();
});
