<?php

namespace Laravel\Nightwatch\Sensors;

use DateTimeInterface;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// TODO: flush and setContainer
// TODO: maybe this should just new them up directly. It does know how to, after all and would be better performance-wise.
final class Sensor
{
    /**
     * @var array{
     *     requests?: RequestSensor,
     *     queries?: QuerySensor,
     *     cache_events?: CacheEventsSensor,
     * }
     */
    private array $sensors = [];

    public function __construct(private Container $app)
    {
        //
    }

    public function requests(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        /** @var RequestSensor */
        $sensor = $this->sensors['requests'] ??= $this->app->make(RequestsSensor::class);

        $sensor($startedAt, $request, $response);
    }

    public function queries(QueryExecuted $event): void
    {
        /** @var QuerySensor */
        $sensor = $this->sensors['queries'] ??= $this->app->make(QueriesSensor::class);

        $sensor($event);
    }

    public function cacheEvents(CacheMissed|CacheHit $event): void
    {
        // TODO extract enum for all these keys we use throughout
        $sensor = $this->sensors['cache_events'] ??= $this->app->make(CacheEventsSensor::class);

        $sensor($event);
    }
}
