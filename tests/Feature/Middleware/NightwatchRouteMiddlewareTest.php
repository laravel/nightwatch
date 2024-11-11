<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\SensorManager;

use function Pest\Laravel\get;

it('gracefully handles exceptions', function () {
    fakeIngest();
    app()->instance(RouteMiddleware::class, new RouteMiddleware(new class extends SensorManager
    {
        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            throw new RuntimeException('Whoops!');
        }
    }));
    Route::get('test-route', fn () => 'ok');

    $response = get('test-route');

    $response->assertOk();
});
