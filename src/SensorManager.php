<?php

namespace Laravel\Nightwatch;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\LogSensor;
use Laravel\Nightwatch\Sensors\MailSensor;
use Laravel\Nightwatch\Sensors\NotificationSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\QueuedJobSensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Sensors\StageSensor;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Monolog\LogRecord;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
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

    private ?ExceptionSensor $exceptionSensor;

    private ?LogSensor $logSensor;

    private ?OutgoingRequestSensor $outgoingRequestSensor;

    private ?QuerySensor $querySensor;

    private ?QueuedJobSensor $queuedJobSensor;

    private ?NotificationSensor $notificationSensor;

    private ?MailSensor $mailSensor;

    private ?StageSensor $stageSensor;

    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
        private Location $location,
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
            requestState: $this->executionState, // @phpstan-ignore argument.type
        );

        $sensor($request, $response);
    }

    public function command(InputInterface $input, int $status): void
    {
        $sensor = new CommandSensor(
            executionState: $this->executionState, // @phpstan-ignore argument.type
        );

        $sensor($input, $status);
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
        );

        $sensor($event, $trace);
    }

    public function cacheEvent(CacheEvent $event): void
    {
        $sensor = $this->cacheEventSensor ??= new CacheEventSensor(
            clock: $this->clock,
            executionState: $this->executionState,
        );

        $sensor($event);
    }

    public function mail(MessageSent $event): void
    {
        $sensor = $this->mailSensor ??= new MailSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function notification(NotificationSent $event): void
    {
        $sensor = $this->notificationSensor ??= new NotificationSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->outgoingRequestSensor ??= new OutgoingRequestSensor(
            executionState: $this->executionState,
        );

        $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    public function exception(Throwable $e): void
    {
        try {
            $sensor = $this->exceptionSensor ??= new ExceptionSensor(
                clock: $this->clock,
                executionState: $this->executionState,
                location: $this->location,
            );

            $sensor($e);
        } catch (Throwable $e) {
            //
        }
    }

    public function log(LogRecord $record): void
    {
        $sensor = $this->logSensor ??= new LogSensor(
            executionState: $this->executionState,
        );

        $sensor($record);
    }

    public function queuedJob(JobQueued $event): void
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            executionState: $this->executionState,
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function prepareForNextInvocation(): void
    {
        // $this->clock->executionStartInMicrotime = $this->clock->microtime();
        // // $this->executionState = new RequestState(
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
