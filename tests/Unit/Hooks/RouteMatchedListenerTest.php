<?php

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;

it('gracefully handles middleware registered as a string', function () {
    $request = Request::create('/users');
    $route = new Route(['GET'], '/users', ['middleware' => 'api']);
    $event = new RouteMatched($route, $request);
    $handler = new RouteMatchedListener(nightwatch());

    expect($route->action['middleware'])->toBe('api');

    $handler($event);

    if (class_exists(Terminating::class)) {
        expect($route->action['middleware'])->toBe(['api', RouteMiddleware::class]);
    } else {
        expect($route->action['middleware'])->toBe([TerminatingMiddleware::class, 'api', RouteMiddleware::class]);
    }
});
