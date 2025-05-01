<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;

/**
 * @internal
 */
final class LoopingListener
{
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(): void
    {
        $this->nightwatch->ingest();
    }
}
