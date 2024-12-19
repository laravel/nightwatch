<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Cache\Events\CacheEvent;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class CacheEventListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(CacheEvent $event): void
    {
        try {
            $this->sensor->cacheEvent($event);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }
}
