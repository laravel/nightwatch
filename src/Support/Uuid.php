<?php

namespace Laravel\Nightwatch\Support;

use function call_user_func;

/**
 * @internal
 */
final class Uuid
{
    /**
     * @param  (callable(): string)  $uuidResolver
     */
    public function __construct(public $uuidResolver)
    {
        //
    }

    public function make(): string
    {
        return call_user_func($this->uuidResolver);
    }
}
