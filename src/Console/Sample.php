<?php

namespace Laravel\Nightwatch\Console;

use Closure;
use Illuminate\Console\Scheduling\Event;
use Laravel\Nightwatch\Facades\Nightwatch;

final class Sample
{
    public static function rate(float $rate = 1.0): Closure
    {
        return fn (Event $event) => Nightwatch::sampleScheduledTask($event, $rate);
    }
}
