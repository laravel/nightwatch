<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    $middleware = new TerminatingMiddleware($sensor, app(ExecutionState::class));
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response('response');
    };

    $response = $middleware->handle($request, $next);

    expect($sensor->thrown)->toBeFalse();
    expect($response->content())->toBe('response');
    expect($nextCalledWith)->toBe($request);

    $middleware->terminate($request, $response);

    expect($sensor->thrown)->toBeTrue();
});

it('handles response types that laravel does not wrap', function () {
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
    $middleware = new TerminatingMiddleware($sensor, app(ExecutionState::class));
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response()->streamDownload(function () {
            echo '...';
        });
    };

    $response = $middleware->handle($request, $next);

    expect($sensor->thrown)->toBeFalse();
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($nextCalledWith)->toBe($request);

    $middleware->terminate($request, $response);

    expect($sensor->thrown)->toBeTrue();
});
