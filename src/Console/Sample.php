<?php

namespace Laravel\Nightwatch\Console;

use Closure;
use Illuminate\Console\Scheduling\Event;
use Laravel\Nightwatch\Core;

use function app;

final class Sample
{
    public static function rate(float $rate = 1.0): Closure
    {
        return static fn (Event $event) => app(Core::class)->sampleScheduledTask($event, $rate);
    }
}
