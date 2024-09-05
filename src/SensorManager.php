<?php

namespace Laravel\Nightwatch;

use Carbon\Carbon;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\QueuedJobSensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Sensors\StageSensor;
use Laravel\Nightwatch\Types\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * TODO refresh application instance.
 * TODO wrap everything in resuce so we never interfere with the running application.
 *
 * @internal
 */
final class SensorManager
{
    private ?CacheEventSensor $cacheEventSensor;

    private ?ExceptionSensor $exceptionSensor;

    private ?OutgoingRequestSensor $outgoingRequestSensor;

    private ?QuerySensor $querySensor;

    private ?QueuedJobSensor $queuedJobSensor;

    private ?StageSensor $stageSensor;

    private ?PeakMemoryProvider $peakMemoryProvider;

    private ?Location $location;

    private ?Config $config;

    private ?UserProvider $userProvider;

    public function __construct(
        private ExecutionState $executionState,
        private Clock $clock,
        private Application $app,
        private RecordsBuffer $recordsBuffer = new RecordsBuffer,
    ) {
        //
    }

    public function stage(ExecutionStage $executionStage): void
    {
        // This cannot be cached because the clock is resolved very early on.
        // We likely need to make the clock mutable rather than replacing the
        // instance.
        $sensor = new StageSensor(
            clock: $this->clock,
            executionState: $this->executionState,
        );

        $sensor($executionStage);
    }

    public function executionStage(): ExecutionStage
    {
        return $this->executionState->stage;
    }

    public function request(Request $request, Response $response): void
    {
        $sensor = new RequestSensor(
            clock: $this->clock,
            executionState: $this->executionState,
            peakMemory: $this->peakMemoryProvider(),
            recordsBuffer: $this->recordsBuffer,
            user: $this->user(),
        );

        $sensor($request, $response);
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
            executionState: $this->executionState,
            peakMemory: $this->peakMemoryProvider(),
            user: $this->user(),
        );

        $sensor($startedAt, $input, $status);
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     */
    public function query(QueryExecuted $event, array $trace): void
    {
        $sensor = $this->querySensor ??= new QuerySensor(
            clock: $this->clock,
            executionState: $this->executionState,
            location: $this->location(),
            recordsBuffer: $this->recordsBuffer,
            user: $this->user(),
        );

        $sensor($event, $trace);
    }

    public function cacheEvent(CacheMissed|CacheHit $event): void
    {
        $sensor = $this->cacheEventSensor ??= new CacheEventSensor(
            recordsBuffer: $this->recordsBuffer,
            executionState: $this->executionState,
            clock: $this->clock,
            user: $this->user(),
        );

        $sensor($event);
    }

    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->outgoingRequestSensor ??= new OutgoingRequestSensor(
            recordsBuffer: $this->recordsBuffer,
            executionState: $this->executionState,
            user: $this->user(),
            clock: $this->clock,
        );

        $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        $sensor = $this->exceptionSensor ??= new ExceptionSensor(
            clock: $this->clock,
            executionState: $this->executionState,
            location: $this->location(),
            recordsBuffer: $this->recordsBuffer,
            user: $this->user(),
            basePath: $this->app->basePath(),
        );

        $sensor($e);
    }

    public function queuedJob(JobQueued $event): void
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            recordsBuffer: $this->recordsBuffer,
            executionState: $this->executionState,
            user: $this->user(),
            clock: $this->clock,
            config: $this->config(),
        );

        $sensor($event);
    }

    private function peakMemoryProvider(): PeakMemoryProvider
    {
        return $this->peakMemoryProvider ??= $this->app->make(PeakMemoryProvider::class);
    }

    public function flush(): string
    {
        return $this->recordsBuffer->flush();
    }

    private function user(): UserProvider
    {
        return $this->userProvider ??= $this->app->make(UserProvider::class);
    }

    private function location(): Location
    {
        return $this->location ??= $this->app->make(Location::class);
    }

    private function config(): Config
    {
        return $this->config ??= $this->app->make('config');
    }

    public function prepareForNextInvocation(): void
    {
        $this->recordsBuffer = new RecordsBuffer;
        $this->clock->executionStartInMicrotime = $this->clock->microtime();
        // $this->executionState = new ExecutionState(
        //     traceId: $traceId = (string) Str::uuid(),
        //     executionId: $traceId,
        // );

        $this->cacheEventSensor = null;
        $this->exceptionSensor = null;
        $this->outgoingRequestSensor = null;
        $this->querySensor = null;
        $this->queuedJobSensor = null;
        // ...
    }
}
