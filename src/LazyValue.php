<?php

namespace Laravel\Nightwatch;

use JsonSerializable;

use function call_user_func;

/**
 * @template TValue
 */
final class LazyValue implements JsonSerializable
{
    /**
     * @param  (callable(): TValue)  $callback
     */
    public function __construct(private $callback)
    {
        //
    }

    /**
     * @return TValue
     */
    public function jsonSerialize(): mixed
    {
        return call_user_func($this->callback);
    }
}
