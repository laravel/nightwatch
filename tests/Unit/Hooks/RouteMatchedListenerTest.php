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
    $handler = new RouteMatchedListener(nightwatch());

    expect($route->action['middleware'])->toBe('api');

    $handler($event);

    if (Compatibility::$terminatingEventExists) {
        expect($route->action['middleware'])->toBe(['api', RouteMiddleware::class]);
    } else {
        expect($route->action['middleware'])->toBe([GlobalMiddleware::class, 'api', RouteMiddleware::class]);
    }
});
