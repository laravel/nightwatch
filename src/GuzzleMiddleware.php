<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Clock;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class GuzzleMiddleware
{
    public function __construct(private SensorManager $sensor, private Clock $clock)
    {
        //
    }

    /**
     * TODO record the failed responses as well.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $startMicrotime = $this->clock->microtime();

            return $handler($request, $options)
                ->then(function (ResponseInterface $response) use ($request, $startMicrotime) {
                    $endMicrotime = $this->clock->microtime();

                    $this->sensor->outgoingRequest(
                        $startMicrotime, $endMicrotime,
                        $request, $response,
                    );

                    return $response;
                });
        };
    }
}
