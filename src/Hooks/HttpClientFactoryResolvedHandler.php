<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class HttpClientFactoryResolvedHandler
{
    public function __construct(private SensorManager $sensor, private Clock $clock)
    {
        //
    }

    public function __invoke(Factory $factory): void
    {
        try {
            // TODO check this isn't a memory leak in octane
            $factory->globalMiddleware(new GuzzleMiddleware($this->sensor, $this->clock));
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
