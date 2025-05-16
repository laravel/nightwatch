<?php

use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('gracefully handles exceptions', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;

    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return 'response';
    };

    $middleware = new RouteMiddleware(nightwatch());
    $response = $middleware->handle($request, $next);

    expect($thrownInStageSensor)->toBeTrue();
    expect($response)->toBe('response');
    expect($nextCalledWith)->toBe($request);
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});

it('handles response types that laravel does not wrap', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;

    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response()->streamDownload(function () {
            echo '...';
        });
    };

    $middleware = new RouteMiddleware(nightwatch());
    $response = $middleware->handle($request, $next);

    expect($thrownInStageSensor)->toBeTrue();
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($nextCalledWith)->toBe($request);
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});
