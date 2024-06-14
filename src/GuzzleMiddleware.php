<?php

namespace Laravel\Nightwatch;

use GuzzleHttp\Promise\RejectedPromise;
use Laravel\Nightwatch\Contracts\Clock;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class GuzzleMiddleware
{
    public function __construct(private SensorManager $sensor, private Clock $clock)
    {
        //
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $startInMicrotime = $this->clock->microtime();

            return $handler($request, $options)
                ->then(function (ResponseInterface $response) use ($request, $startInMicrotime) {
                    $durationInMicrotime = $this->clock->diffInMicrotime($startInMicrotime);

                    $this->sensor->outgoingRequest(
                        $startInMicrotime, $durationInMicrotime,
                        $request, $response,
                    );

                    return $response;
                }, function (Throwable $exception) {
                    // TODO does this only get RequestExceptions and can we
                    // get the response from it?
                    // $this->sensor->outgoingRequests($startedAt, $request, $response);

                    return new RejectedPromise($exception);
                });
        };
    }
}
