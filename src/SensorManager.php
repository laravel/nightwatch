<?php

namespace Laravel\Nightwatch;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Sensors\CacheEventSensor;
use Laravel\Nightwatch\Sensors\CommandSensor;
use Laravel\Nightwatch\Sensors\ExceptionSensor;
use Laravel\Nightwatch\Sensors\JobAttemptSensor;
use Laravel\Nightwatch\Sensors\LogSensor;
use Laravel\Nightwatch\Sensors\MailSensor;
use Laravel\Nightwatch\Sensors\NotificationSensor;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;
use Laravel\Nightwatch\Sensors\QuerySensor;
use Laravel\Nightwatch\Sensors\QueuedJobSensor;
use Laravel\Nightwatch\Sensors\RequestSensor;
use Laravel\Nightwatch\Sensors\ScheduledTaskSensor;
use Laravel\Nightwatch\Sensors\StageSensor;
use Laravel\Nightwatch\Sensors\UserSensor;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
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
final class SensorManager
{
    /**
     * @var (callable(CacheEvent): void)|null
     */
    public $cacheEventSensor;

    /**
     * @var (callable(Throwable, null|bool): void)|null
     */
    public $exceptionSensor;

    /**
     * @var (callable(LogRecord): void)|null
     */
    public $logSensor;

    /**
     * @var (callable(float, float, RequestInterface, ResponseInterface): void)|null
     */
    public $outgoingRequestSensor;

    /**
     * @var (callable(QueryExecuted, list<array{ file?: string, line?: int }>): void)|null
     */
    public $querySensor;

    /**
     * @var (callable(JobQueueing|JobQueued): void)|null
     */
    public $queuedJobSensor;

    /**
     * @var (callable(JobProcessed|JobReleasedAfterException|JobFailed): void)|null
     */
    public $jobAttemptSensor;

    /**
     * @var (callable(NotificationSending|NotificationSent): void)|null
     */
    public $notificationSensor;

    /**
     * @var (callable(MessageSending|MessageSent): void)|null
     */
    public $mailSensor;

    /**
     * @var (callable(): void)|null
     */
    public $userSensor;

    /**
     * @var (callable(ExecutionStage): void)|null
     */
    public $stageSensor;

    /**
     * @var (callable(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed): void)|null
     */
    public $scheduledTaskSensor;

    /**
     * @var (callable(Request, Response): void)|null
     */
    public $requestSensor;

    /**
     * @var (callable(InputInterface, int): void)|null
     */
    public $commandSensor;

    public function __construct(
        public Ingest $ingest,
        private RequestState|CommandState $executionState,
        private Clock $clock,
        public Location $location,
        private Repository $config,
    ) {
        //
    }

    public function stage(ExecutionStage $executionStage): void
    {
        $sensor = $this->stageSensor ??= new StageSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($executionStage);
    }

    public function request(Request $request, Response $response): void
    {
        $sensor = $this->requestSensor ??= new RequestSensor(
            ingest: $this->ingest,
            requestState: $this->executionState, // @phpstan-ignore argument.type
        );

        $sensor($request, $response);
    }

    public function command(InputInterface $input, int $status): void
    {
        $sensor = $this->commandSensor ??= new CommandSensor(
            ingest: $this->ingest,
            commandState: $this->executionState, // @phpstan-ignore argument.type
        );

        $sensor($input, $status);
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     */
    public function query(QueryExecuted $event, array $trace): void
    {
        $sensor = $this->querySensor ??= new QuerySensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
            location: $this->location,
        );

        $sensor($event, $trace);
    }

    public function cacheEvent(CacheEvent $event): void
    {
        $sensor = $this->cacheEventSensor ??= new CacheEventSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function mail(MessageSending|MessageSent $event): void
    {
        $sensor = $this->mailSensor ??= new MailSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function notification(NotificationSending|NotificationSent $event): void
    {
        $sensor = $this->notificationSensor ??= new NotificationSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $sensor = $this->outgoingRequestSensor ??= new OutgoingRequestSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
        );

        $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    public function exception(Throwable $e, ?bool $handled): void
    {
        $sensor = $this->exceptionSensor ??= new ExceptionSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
            location: $this->location,
        );

        $sensor($e, $handled);
    }

    public function log(LogRecord $record): void
    {
        $sensor = $this->logSensor ??= new LogSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
        );

        $sensor($record);
    }

    public function queuedJob(JobQueueing|JobQueued $event): void
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            ingest: $this->ingest,
            executionState: $this->executionState,
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function jobAttempt(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        $sensor = $this->jobAttemptSensor ??= new JobAttemptSensor(
            ingest: $this->ingest,
            commandState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        $sensor = $this->scheduledTaskSensor ??= new ScheduledTaskSensor(
            ingest: $this->ingest,
            commandState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function user(): void
    {
        $sensor = $this->userSensor ??= new UserSensor(
            ingest: $this->ingest,
            requestState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        $sensor();
    }

    public function flush(): void
    {
        $this->cacheEventSensor = null;
        $this->exceptionSensor = null;
        $this->logSensor = null;
        $this->outgoingRequestSensor = null;
        $this->querySensor = null;
        $this->queuedJobSensor = null;
        $this->jobAttemptSensor = null;
        $this->notificationSensor = null;
        $this->mailSensor = null;
        $this->userSensor = null;
        $this->stageSensor = null;
        $this->scheduledTaskSensor = null;
        $this->requestSensor = null;
        $this->commandSensor = null;
    }
}
