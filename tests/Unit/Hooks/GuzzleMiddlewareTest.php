<?php

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;

it('gracefully handles exceptions in the before middleware', function () {
    $exceptions = [];
    nightwatch()->sensor->exceptionSensor = function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    };
    $thrownInMicrotimeResolver = false;
    nightwatch()->clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver): float {
        $thrownInMicrotimeResolver = true;

        throw new RuntimeException('Whoops!');
    };

    $middleware = new GuzzleMiddleware(nightwatch());

    $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));
    $response = $stack(new Request('GET', '/test'), [])->wait();

    expect($thrownInMicrotimeResolver)->toBeTrue();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops!');
    expect((string) $response->getBody())->toBe('ok');
});

it('gracefully handles exceptions in the after middleware', function () {
    $thrownInOutgoingRequestSensor = false;
    nightwatch()->sensor->outgoingRequestSensor = function () use (&$thrownInOutgoingRequestSensor) {
        $thrownInOutgoingRequestSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $middleware = new GuzzleMiddleware(nightwatch());
    $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));

    $response = $stack(new Request('GET', '/test'), [])->wait();

    expect($thrownInOutgoingRequestSensor)->toBeTrue();
    expect((string) $response->getBody())->toBe('ok');
    expect(nightwatch()->state->exceptions)->toBe(1);
});
