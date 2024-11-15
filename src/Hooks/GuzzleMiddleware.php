<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\SensorManager;
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
            try {
                $startMicrotime = $this->clock->microtime();
            } catch (Exception $e) {
                return $handler($request, $options);
            }

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($request, $startMicrotime) {
                try {
                    $endMicrotime = $this->clock->microtime();

                    $this->sensor->outgoingRequest(
                        $startMicrotime, $endMicrotime,
                        $request, $response,
                    );
                } catch (Exception $e) {
                    //
                }

                return $response;
            });
        };
    }
}
