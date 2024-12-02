<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestBootedHandler;
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
    $handler = new RequestBootedHandler($sensor);

    $handler(app());

    expect($sensor->thrown)->toBeTrue();
});
