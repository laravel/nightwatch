<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\NightwatchTerminatingMiddleware;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

use function Pest\Laravel\get;

it('gracefully handles exceptions', function () {
    fakeIngest();
    app()->instance(NightwatchTerminatingMiddleware::class, new NightwatchTerminatingMiddleware(new class extends SensorManager
    {
        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            throw new RuntimeException('Whoops!');
        }
    }, app(ExecutionState::class)));
    Route::get('test-route', fn () => 'ok');

    $response = get('test-route');

    $response->assertOk();
});
