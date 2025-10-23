<?php

namespace Laravel\NightwatchAgent;

use Laravel\NightwatchAgent\Contracts\Clock as ClockContract;

use function time;

class Clock implements ClockContract
{
    public function time(): int
    {
        return time();
    }
}
