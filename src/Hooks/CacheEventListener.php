<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;

final class CacheEventListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(RetrievingKey|CacheMissed|CacheHit|WritingKey|KeyWritten $event): void
    {
        try {
            $this->sensor->cacheEvent($event);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
