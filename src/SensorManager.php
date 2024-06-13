<?php

namespace Laravel\Nightwatch;

use Carbon\Carbon;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Types\TinyText;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

    private ?string $traceId;

    private ?string $deployId;

    private ?string $server;

    public RecordsBuffer $recordsBuffer;

    private ExecutionParent $executionParent;

    private ?PeakMemoryProvider $peakMemoryProvider = null;

    public function __construct(private Container $app)
    {
        $this->recordsBuffer = new RecordsBuffer;
        $this->executionParent = new ExecutionParent;
    }

    public function request(Carbon $startedAt, Request $request, Response $response): void
    {
        $sensor = new RequestSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            peakMemory: $this->peakMemoryProvider(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    public function command(Carbon $startedAt, InputInterface $input, int $status): void
    {
        // TODO should we cache this for commands that are run within a request? Do they even register here?
        $sensor = new CommandSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            peakMemory: $this->peakMemoryProvider(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $input, $status);
    }

    public function query(QueryExecuted $event): void
    {
        $sensor = $this->sensors['queries'] ??= new QuerySensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
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
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function outgoingRequest(float $start, float $duration, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->sensors['outgoing_requests'] ??= new OutgoingRequestSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($start, $duration, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        $sensor = $this->sensors['exceptions'] ??= new ExceptionSensor(
            recordsBuffer: $this->recordsBuffer,
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
        return $this->deployId ??= TinyText::limit((string) $this->app->make(Config::class)->get('nightwatch.deploy_id'));
    }

    private function server(): string
    {
        return $this->server ??= TinyText::limit((string) $this->app->make(Config::class)->get('nightwatch.server'));
    }

    public function flush(): string
    {
        return $this->recordsBuffer->flush();
    }

    public function prepareForNextInvocation(): void
    {
        $this->recordsBuffer = new RecordsBuffer;
        $this->executionParent = new ExecutionParent;
        $this->sensors = [];
        $this->traceId = null;
    }
}
