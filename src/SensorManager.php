<?php

namespace Laravel\Nightwatch;

use Carbon\Carbon;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\QueuedJobSensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Types\TinyText;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * TODO refresh application instance.
 * TODO migrate from array of sensors individual properties
 */
final class SensorManager
{
    /**
     * @var array{
     *     queries?: QuerySensor,
     *     cache_events?: CacheEventSensor,
     *     outgoing_requests?: OutgoingRequestSensor,
     *     exceptions?: ExceptionSensor,
     *     queued_jobs?: QueuedJobSensor,
     * }
     */
    private array $sensors = [];

    private ?string $traceId;

    private ?string $deployId;

    private ?string $server;

    public RecordsBuffer $recordsBuffer;

    private ExecutionParent $executionParent;

    private ?PeakMemoryProvider $peakMemoryProvider = null;

    private ?UserProvider $userProvider = null;

    private ?Clock $clock;

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
            user: $this->user(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startedAt, $request, $response);
    }

    /**
     * TODO should we cache this one for commands that run within a request?
     * TODO if they do trigger, should we not listen to any commands in a request
     * lifecycle? Push that out to the service provider not here.
     */
    public function command(Carbon $startedAt, InputInterface $input, int $status): void
    {
        $sensor = new CommandSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            peakMemory: $this->peakMemoryProvider(),
            user: $this->user(),
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
            user: $this->user(),
            clock: $this->clock(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function cacheEvent(CacheMissed|CacheHit $event): void
    {
        $sensor = $this->sensors['cache_events'] ??= new CacheEventSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            clock: $this->clock(),
            user: $this->user(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
    }

    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->sensors['outgoing_requests'] ??= new OutgoingRequestSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            user: $this->user(),
            clock: $this->clock(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        $sensor = $this->sensors['exceptions'] ??= new ExceptionSensor(
            recordsBuffer: $this->recordsBuffer,
            user: $this->user(),
            clock: $this->clock(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($e);
    }

    public function queuedJob(JobQueued $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        $sensor = $this->sensors['queued_jobs'] ??= new QueuedJobSensor(
            recordsBuffer: $this->recordsBuffer,
            executionParent: $this->executionParent,
            user: $this->user(),
            clock: $this->clock(),
            traceId: $this->traceId(),
            deployId: $this->deployId(),
            server: $this->server(),
        );

        $sensor($event);
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

    public function user(): UserProvider
    {
        return $this->userProvider ??= $this->app->make(UserProvider::class);
    }

    private function clock(): Clock
    {
        return $this->clock ??= $this->app->make(Clock::class);
    }

    public function prepareForNextInvocation(): void
    {
        $this->recordsBuffer = new RecordsBuffer;
        $this->executionParent = new ExecutionParent;
        $this->clock = null;
        $this->sensors = [];
        $this->traceId = null;
    }
}
