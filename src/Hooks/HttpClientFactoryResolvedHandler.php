<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\SensorManager;

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
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
