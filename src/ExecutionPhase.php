<?php

namespace Laravel\Nightwatch;

/**
 * @internal
 */
enum ExecutionPhase: string
{
    case Bootstrap = 'bootstrap';
    case BeforeMiddleware = 'before_middleware';
    case Action = 'action';
    case Render = 'render';
    case AfterMiddleware = 'after_middleware';
    case Sending = 'sending';
    case Terminating = 'terminating';
}
