<?php

namespace Laravel\Nightwatch;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\MailSensor;
use Laravel\Nightwatch\Sensors\NotificationSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\QueuedJobSensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Sensors\StageSensor;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * TODO refresh application instance.
 *
 * @internal
 */
class SensorManager
{
    private ?CacheEventSensor $cacheEventSensor;

    private ?CommandSensor $commandSensor;

    private ?ExceptionSensor $exceptionSensor;

    private ?OutgoingRequestSensor $outgoingRequestSensor;

    private ?QuerySensor $querySensor;

    private ?QueuedJobSensor $queuedJobSensor;

    private ?NotificationSensor $notificationSensor;

    private ?MailSensor $mailSensor;

    private ?StageSensor $stageSensor;

    public function __construct(
        private ExecutionState $executionState,
        private Clock $clock,
        private Location $location,
        private UserProvider $user,
        private Repository $config,
    ) {
        //
    }

    public function stage(ExecutionStage $executionStage): void
    {
        $sensor = $this->stageSensor ??= new StageSensor(
            clock: $this->clock,
            executionState: $this->executionState,
        );

        $sensor($executionStage);
    }

    public function request(Request $request, Response $response): void
    {
        $sensor = new RequestSensor(
            executionState: $this->executionState,
            user: $this->user,
        );

        $sensor($request, $response);
    }

    /**
     * TODO should we cache this one for commands that run within a request?
     * TODO if they do trigger, should we not listen to any commands in a request
     * lifecycle? Push that out to the service provider not here.
     */
    public function command(CommandStarting|CommandFinished $event): void
    {
        $sensor = $this->commandSensor ??= new CommandSensor(
            clock: $this->clock,
            executionState: $this->executionState,
        );

        $sensor($event);
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     */
    public function query(QueryExecuted $event, array $trace): void
    {
        $sensor = $this->querySensor ??= new QuerySensor(
            clock: $this->clock,
            executionState: $this->executionState,
            location: $this->location,
            user: $this->user,
        );

        $sensor($event, $trace);
    }

    public function cacheEvent(CacheEvent $event): void
    {
        $sensor = $this->cacheEventSensor ??= new CacheEventSensor(
            clock: $this->clock,
            executionState: $this->executionState,
            user: $this->user,
        );

        $sensor($event);
    }

    public function mail(MessageSent $event): void
    {
        $sensor = $this->mailSensor ??= new MailSensor(
            executionState: $this->executionState,
            user: $this->user,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function notification(NotificationSent $event): void
    {
        $sensor = $this->notificationSensor ??= new NotificationSensor(
            executionState: $this->executionState,
            user: $this->user,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->outgoingRequestSensor ??= new OutgoingRequestSensor(
            executionState: $this->executionState,
            user: $this->user,
        );

        $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        $sensor = $this->exceptionSensor ??= new ExceptionSensor(
            clock: $this->clock,
            executionState: $this->executionState,
            location: $this->location,
            user: $this->user,
        );

        $sensor($e);
    }

    public function queuedJob(JobQueued $event): void
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            executionState: $this->executionState,
            user: $this->user,
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function prepareForNextInvocation(): void
    {
        // $this->clock->executionStartInMicrotime = $this->clock->microtime();
        // // $this->executionState = new ExecutionState(
        // //     traceId: $traceId = (string) Str::uuid(),
        // //     executionId: $traceId,
        // // );

        // $this->cacheEventSensor = null;
        // $this->exceptionSensor = null;
        // $this->outgoingRequestSensor = null;
        // $this->querySensor = null;
        // $this->queuedJobSensor = null;
        // // ...
    }
}
