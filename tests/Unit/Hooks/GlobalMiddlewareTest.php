<?php

use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('gracefully handles exceptions when the terminating event doesn\'t exist', function () {
    Compatibility::$terminatingEventExists = false;
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Bootstrap;

    $middleware = new GlobalMiddleware(nightwatch());
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response('response');
    };

    $response = $middleware->handle($request, $next);

    expect($thrownInStageSensor)->toBeFalse();
    expect($response->content())->toBe('response');
    expect($nextCalledWith)->toBe($request);

    $middleware->terminate($request, $response);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});

it('handles response types that laravel does not wrap', function () {
    Compatibility::$terminatingEventExists = false;
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Bootstrap;

    $middleware = new GlobalMiddleware(nightwatch());
    $request = Request::create('/test');
    $nextCalledWith = null;
    $next = function ($request) use (&$nextCalledWith) {
        $nextCalledWith = $request;

        return response()->streamDownload(function () {
            echo '...';
        });
    };

    $response = $middleware->handle($request, $next);

    expect($thrownInStageSensor)->toBeFalse();
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($nextCalledWith)->toBe($request);

    $middleware->terminate($request, $response);

    expect($thrownInStageSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
