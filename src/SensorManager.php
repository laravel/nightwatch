<?php

namespace Laravel\Nightwatch;

use DateTimeInterface;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// TODO: flush caches (peak memory does not need to be flushed)
// Do we need to refresh the application instance?
final class SensorManager
{
    /**
     * @var array{
     *     queries?: QueriesSensor,
     *     cache_events?: CacheEventsSensor,
     *     outgoing_requests?: OutgoingRequestsSensor,
     *     exceptions?: ExceptionsSensor,
     * }
     */
    private array $sensors = [];

    private string $traceId;

    private string $deployId;

    private string $server;

    private RecordCollection $records;

    private PeakMemoryProvider $peakMemoryProvider;

    public function __construct(
        private Container $app,
    ) {
        //
    }

    public function request(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $sensor = new RequestSensor(
            records: $this->records(),
            peakMemory: $this->peakMemoryProvider(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    public function query(QueryExecuted $event): void
    {
        $sensor = $this->sensors['queries'] ??= new QuerySensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function cacheEvent(CacheMissed|CacheHit $event): void
    {
        // TODO extract enum for all these keys we use throughout
        $sensor = $this->sensors['cache_events'] ??= new CacheEventSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function outgoingRequest(DateTimeInterface $startedAt, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->sensors['outgoing_requests'] ??= new OutgoingRequestSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        $sensor = $this->sensors['exceptions'] ??= new ExceptionSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($e);
    }

    private function traceId(): string
    {
        return $this->traceId ??= $this->app->make('laravel.nightwatch.trace_id');
    }

    private function peakMemoryProvider(): PeakMemoryProvider
    {
        return $this->peakMemoryProvider ??= $this->app->make(PeakMemoryProvider::class);
    }

    private function deployId(): string
    {
        return $this->deployId ??= (string) $this->app->make(Config::class)->get('nightwatch.deploy_id');
    }

    private function server(): string
    {
        return $this->server ??= (string) $this->app->make(Config::class)->get('nightwatch.server');
    }

    private function records(): RecordCollection
    {
        return $this->records ??= $this->app->make(RecordCollection::class);
    }
}
