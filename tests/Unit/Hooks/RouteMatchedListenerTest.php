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

    $this->assertSame('api', $route->action['middleware']);

    $handler = new RouteMatchedListener(nightwatch());
    $handler($event);

    if (Compatibility::$terminatingEventExists) {
        $this->assertSame(['api', RouteMiddleware::class], $route->action['middleware']);
    } else {
        $this->assertSame([GlobalMiddleware::class, 'api', RouteMiddleware::class], $route->action['middleware']);
    }
});

it('gracefully handles exceptions', function () {
    $request = Request::create('/users');
    $route = new Route(['GET'], '/users', []);
    $route->action = 5;
    $event = new RouteMatched($route, $request);

    $handler = new RouteMatchedListener(nightwatch());
    $handler($event);

    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
