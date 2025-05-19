<?php

namespace Tests\Unit\Hooks;

use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Tests\TestCase;

class RouteMatchedListenerTest extends TestCase
{
    public function test_it_gracefully_handles_middleware_registered_as_a_string(): void
    {
        $request = Request::create('/users');
        $route = new Route(['GET'], '/users', ['middleware' => 'api']);
        $event = new RouteMatched($route, $request);

        $this->assertSame('api', $route->action['middleware']);

        $handler = new RouteMatchedListener($this->core);
        $handler($event);

        if (Compatibility::$terminatingEventExists) {
            $this->assertSame(['api', RouteMiddleware::class], $route->action['middleware']);
        } else {
            $this->assertSame([GlobalMiddleware::class, 'api', RouteMiddleware::class], $route->action['middleware']);
        }
    }

    public function test_it_gracefully_handles_exceptions(): void
    {
        $request = Request::create('/users');
        $route = new Route(['GET'], '/users', []);
        $route->action = 5;
        $event = new RouteMatched($route, $request);

        $handler = new RouteMatchedListener($this->core);
        $handler($event);

        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
