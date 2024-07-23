<?php

namespace Laravel\Nightwatch;

/**
 * @internal
 */
enum ExecutionPhase: string
{
    case Bootstrap = 'bootstrap';
    case GlobalBeforeMiddleware = 'global_before_middleware';
    case RouteBeforeMiddleware = 'route_before_middleware';
    case Main = 'main';
    case MainRender = 'main_render';
    case RouteAfterMiddleware = 'route_after_middleware';
    case RouteAfterMiddlewareRender = 'route_after_middleware_render';
    case GlobalAfterMiddleware = 'global_after_middleware';
    case ResponseTransmission = 'response_transmission';
    case Terminate = 'terminate';
}
