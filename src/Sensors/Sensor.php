<?php

namespace Laravel\Nightwatch\Sensors;

use DateTimeInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;
use Symfony\Component\HttpFoundation\Response;

// TODO: flush caches (peak memory does not need to be flushed)
final class Sensor
{
    /**
     * @var array{
     *     requests?: RequestsSensor,
     *     queries?: QueriesSensor,
     *     cache_events?: CacheEventsSensor,
     *     outgoing_requests?: OutgoingRequestsSensor,
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

    public function requests(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $sensor = $this->sensors['requests'] ??= new RequestsSensor(
            records: $this->records(),
            peakMemory: $this->peakMemoryProvider(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    public function queries(QueryExecuted $event): void
    {
        $sensor = $this->sensors['queries'] ??= new QueriesSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function cacheEvents(CacheMissed|CacheHit $event): void
    {
        // TODO extract enum for all these keys we use throughout
        $sensor = $this->sensors['cache_events'] ??= new CacheEventsSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function outgoingRequests(DateTimeInterface $startedAt, Psr7Request $request, Psr7Response $response): void
    {
        $sensor = $this->sensors['outgoing_requests'] ??= new OutgoingRequestsSensor(
            records: $this->records(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    private function traceId(): string
    {
        return $this->traceId ??= $this->app->make('nightwatch.trace_id');
    }

    private function peakMemoryProvider(): PeakMemoryProvider
    {
        return $this->peakMemoryProvider ??= $this->app->make(PeakMemoryProvider::class);
    }

    private function deployId(): string
    {
        return $this->deployId ??= $this->app->make('config')->get('nightwatch.deploy_id');
    }

    private function server(): string
    {
        return $this->server ??= $this->app->make('config')->get('nightwatch.server');
    }

    private function records(): RecordCollection
    {
        return $this->records ??= $this->app->make(RecordCollection::class);
    }
}
