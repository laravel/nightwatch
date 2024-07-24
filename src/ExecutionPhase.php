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
    case End = 'end';

    public function previous(): ?self
    {
        return match ($this) {
            self::Bootstrap => null,
            self::BeforeMiddleware => self::Bootstrap,
            self::Action => self::BeforeMiddleware,
            self::Render => self::Action,
            self::AfterMiddleware => self::Render,
            self::Sending => self::AfterMiddleware,
            self::Terminating => self::Sending,
            self::End => self::Terminating,
        };
    }
}
