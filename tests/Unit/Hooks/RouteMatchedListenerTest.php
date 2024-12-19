<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles middleware registered as a string', function () {
    $request = Request::create('/users');
    $route = new Route(['GET'], '/users', ['middleware' => 'api']);
    $event = new RouteMatched($route, $request);
    $handler = new RouteMatchedListener(app(SensorManager::class));

    expect($route->action['middleware'])->toBe('api');

    $handler($event);

    expect($route->action['middleware'])->toBe(['api', RouteMiddleware::class]);
});
