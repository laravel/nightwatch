<?php

namespace Laravel\Nightwatch;

use Closure;

use function call_user_func;
use function microtime;

/**
 * @internal
 */
final class Clock
{
    /**
     * @var (Closure(): float)
     */
    public Closure $microtimeResolver;

    public function __construct(public float $executionStartInMicrotime)
    {
        $this->microtimeResolver = fn () => microtime(true);
    }

    public function microtime(): float
    {
        return call_user_func($this->microtimeResolver);
    }

    public function diffInMicrotime(float $start): float
    {
        return call_user_func($this->microtimeResolver) - $start;
    }

    public function executionStartInMicrotime(): float
    {
        return $this->executionStartInMicrotime;
    }
}
