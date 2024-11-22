<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;

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
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
