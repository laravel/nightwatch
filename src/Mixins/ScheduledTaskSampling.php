<?php

namespace Laravel\Nightwatch\Mixins;

use Closure;
use Illuminate\Console\Scheduling\Event;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * @internal
 */
final class ScheduledTaskSampling
{
    public function nightwatchSample(): Closure
    {
        return function (float $rate = 1.0) {
            /** @var Event $this */
            Nightwatch::sampleScheduledTask($this, $rate);

            return $this;
        };
    }

    public function nightwatchDontSample(): Closure
    {
        return function () {
            /** @var Event $this */
            Nightwatch::sampleScheduledTask($this, 0);

            return $this;
        };
    }
}
