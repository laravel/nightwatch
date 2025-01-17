<?php

use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    $middleware = new RouteMiddleware($nightwatch);
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($sensor->thrown)->toBeTrue();
    expect($response)->toBe('response');
    expect($nextCalledWith)->toBe($request);
});

it('handles response types that laravel does not wrap', function () {
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
    $middleware = new RouteMiddleware($nightwatch);
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response()->streamDownload(function () {
            echo '...';
        });
    };

    $response = $middleware->handle($request, $next);

    expect($sensor->thrown)->toBeTrue();
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($nextCalledWith)->toBe($request);
});
