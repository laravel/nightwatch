<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;

it('gracefully handles middleware registered as a string', function () {
    $request = Request::create('/users');
    $route = new Route(['GET'], '/users', ['middleware' => 'api']);
    $event = new RouteMatched($route, $request);

    expect($route->action['middleware'])->toBe('api');

    $handler = new RouteMatchedListener(nightwatch());
    $handler($event);

    if (Compatibility::$terminatingEventExists) {
        expect($route->action['middleware'])->toBe(['api', RouteMiddleware::class]);
    } else {
        expect($route->action['middleware'])->toBe([GlobalMiddleware::class, 'api', RouteMiddleware::class]);
    }
});

it('gracefully handles exceptions', function () {
    $request = Request::create('/users');
    $route = new Route(['GET'], '/users', []);
    $route->action = 5;
    $event = new RouteMatched($route, $request);

    $handler = new RouteMatchedListener(nightwatch());
    $handler($event);

    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
