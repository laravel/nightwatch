<?php

namespace Laravel\Nightwatch\Concerns;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;
use Laravel\Nightwatch\Records\ScheduledTask;

trait InterceptsState
{
    /**
     * @var ?callable(CacheEvent): bool
     */
    private $cacheEventInterceptor = null;

    /**
     * @var ?callable(Command): bool
     */
    private $commandInterceptor = null;

    /**
     * @var ?callable(JobAttempt): bool
     */
    private $jobAttemptInterceptor = null;

    /**
     * @var ?callable(Mail): bool
     */
    private $mailInterceptor = null;

    /**
     * @var ?callable(Notification): bool
     */
    private $notificationInterceptor = null;

    /**
     * @var ?callable(OutgoingRequest): bool
     */
    private $outgoingRequestInterceptor = null;

    /**
     * @var ?callable(Query): bool
     */
    private $queryInterceptor = null;

    /**
     * @var ?callable(QueuedJob): bool
     */
    private $queuedJobInterceptor = null;

    /**
     * @var ?callable(Request): bool
     */
    private $requestInterceptor = null;

    /**
     * @var ?callable(ScheduledTask): bool
     */
    private $scheduledTaskInterceptor = null;

    /**
     * @api
     *
     * @param  (callable(CacheEventRecord): bool)  $callback
     */
    public function interceptCacheEvents(callable $callback): void
    {
        $this->cacheEventInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(Command): bool)  $callback
     */
    public function interceptCommands(callable $callback): void
    {
        $this->commandInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(JobAttempt): bool)  $callback
     */
    public function interceptJobAttempts(callable $callback): void
    {
        $this->jobAttemptInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(Mail): bool)  $callback
     */
    public function interceptMail(callable $callback): void
    {
        $this->mailInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Notification): bool  $callback
     */
    public function interceptNotifications(callable $callback): void
    {
        $this->notificationInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(OutgoingRequest): bool)  $callback
     */
    public function interceptOutgoingRequests(callable $callback): void
    {
        $this->outgoingRequestInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(Query): bool)  $callback
     */
    public function interceptQueries(callable $callback): void
    {
        $this->queryInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(QueuedJob): bool)  $callback
     */
    public function interceptQueuedJob(callable $callback): void
    {
        $this->queuedJobInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(Request): bool)  $callback
     */
    public function interceptRequests(callable $callback): void
    {
        $this->requestInterceptor = $callback;
    }

    /**
     * @api
     *
     * @param  (callable(ScheduledTask): bool)  $callback
     */
    public function interceptScheduledTasks(callable $callback): void
    {
        $this->scheduledTaskInterceptor = $callback;
    }

    private function intercept(CacheEvent|Command|JobAttempt|Mail|Notification|OutgoingRequest|Query|QueuedJob|Request|ScheduledTask $record): bool
    {
        $interceptor = match ($record::class) {
            CacheEvent::class => $this->cacheEventInterceptor,
            Command::class => $this->commandInterceptor,
            JobAttempt::class => $this->jobAttemptInterceptor,
            Mail::class => $this->mailInterceptor,
            Notification::class => $this->notificationInterceptor,
            OutgoingRequest::class => $this->outgoingRequestInterceptor,
            Query::class => $this->queryInterceptor,
            QueuedJob::class => $this->queuedJobInterceptor,
            Request::class => $this->requestInterceptor,
            ScheduledTask::class => $this->scheduledTaskInterceptor,
        };

        return $interceptor === null || $interceptor($record);
    }
}
