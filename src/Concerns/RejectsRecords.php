<?php

namespace Laravel\Nightwatch\Concerns;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;

trait RejectsRecords
{
    /**
     * @var ?callable(CacheEvent): bool
     */
    private $rejectCacheEventCallback = null;

    /**
     * @var ?callable(Mail): bool
     */
    private $rejectMailCallback = null;

    /**
     * @var ?callable(Notification): bool
     */
    private $rejectNotificationCallback = null;

    /**
     * @var ?callable(OutgoingRequest): bool
     */
    private $rejectOutgoingRequestCallback = null;

    /**
     * @var ?callable(Query): bool
     */
    private $rejectQueryCallback = null;

    /**
     * @var ?callable(QueuedJob): bool
     */
    private $rejectQueuedJobCallback = null;

    /**
     * @api
     *
     * @param  callable(CacheEvent): bool  $callback
     */
    public function rejectCacheEvents(callable $callback): void
    {
        $this->rejectCacheEventCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Mail): bool  $callback
     */
    public function rejectMail(callable $callback): void
    {
        $this->rejectMailCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Notification): bool  $callback
     */
    public function rejectNotifications(callable $callback): void
    {
        $this->rejectNotificationCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(OutgoingRequest): bool  $callback
     */
    public function rejectOutgoingRequests(callable $callback): void
    {
        $this->rejectOutgoingRequestCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Query): bool  $callback
     */
    public function rejectQueries(callable $callback): void
    {
        $this->rejectQueryCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(QueuedJob): bool  $callback
     */
    public function rejectQueuedJobs(callable $callback): void
    {
        $this->rejectQueuedJobCallback = $callback;
    }
}
