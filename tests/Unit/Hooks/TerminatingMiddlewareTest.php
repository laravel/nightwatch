<?php

use Illuminate\Support\Env;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '1');
});

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
    $middleware = new TerminatingMiddleware($sensor, app(RequestState::class));
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
    $middleware = new TerminatingMiddleware($sensor, app(RequestState::class));
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
