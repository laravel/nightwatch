<?php

namespace Laravel\Nightwatch;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class GuzzleMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // Can use use `hrtime` instead of Carbon here? Otherwise, just
            // the int value of milliseconds
            $startedAt = CarbonImmutable::now();

            return $handler($request, $options)
                ->then(function (ResponseInterface $response) use ($request, $startedAt) {
                    $this->sensor->outgoingRequest($startedAt, $request, $response);

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
