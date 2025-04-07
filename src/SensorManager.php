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
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
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
use RuntimeException;
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
     * @var (callable(Throwable): void)|null
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
     * @var (callable(JobAttempted): void)|null
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
        private RequestState|CommandState $executionState,
        private Clock $clock,
        public Location $location,
        private Repository $config,
    ) {
        //
    }

    public function stage(ExecutionStage $executionStage): void
    {
        if ($this->executionState->stage === $executionStage) {
            throw new RuntimeException("Cannot transition to the same stage [{$executionStage->value}].");
        }

        $sensor = $this->stageSensor ??= new StageSensor(
            clock: $this->clock,
            executionState: $this->executionState,
        );

        $sensor($executionStage);
    }

    public function request(Request $request, Response $response): void
    {
        $sensor = $this->requestSensor ??= new RequestSensor(
            requestState: $this->executionState, // @phpstan-ignore argument.type
        );

        $sensor($request, $response);
    }

    public function command(InputInterface $input, int $status): void
    {
        $sensor = $this->commandSensor ??= new CommandSensor(
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

    public function mail(MessageSending|MessageSent $event): void
    {
        $sensor = $this->mailSensor ??= new MailSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function notification(NotificationSending|NotificationSent $event): void
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
        $sensor = $this->exceptionSensor ??= new ExceptionSensor(
            clock: $this->clock,
            executionState: $this->executionState,
            location: $this->location,
        );

        $sensor($e);
    }

    public function log(LogRecord $record): void
    {
        $sensor = $this->logSensor ??= new LogSensor(
            executionState: $this->executionState,
        );

        $sensor($record);
    }

    public function queuedJob(JobQueueing|JobQueued $event): void
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            executionState: $this->executionState,
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function jobAttempt(JobAttempted $event): void
    {
        $sensor = $this->jobAttemptSensor ??= new JobAttemptSensor(
            executionState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
            connectionConfig: $this->config->all()['queue']['connections'] ?? [],
        );

        $sensor($event);
    }

    public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        $sensor = $this->scheduledTaskSensor ??= new ScheduledTaskSensor(
            executionState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        $sensor($event);
    }

    public function user(): void
    {
        $sensor = $this->userSensor ??= new UserSensor(
            requestState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        $sensor();
    }
}
