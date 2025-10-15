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
     * @var array<callable(CacheEvent): bool>
     */
    private $rejectCacheEventCallbacks = [];

    /**
     * @var array<callable(Mail): bool>
     */
    private $rejectMailCallbacks = [];

    /**
     * @var array<callable(Notification): bool>
     */
    private $rejectNotificationCallbacks = [];

    /**
     * @var array<callable(OutgoingRequest): bool>
     */
    private $rejectOutgoingRequestCallbacks = [];

    /**
     * @var array<callable(Query): bool>
     */
    private $rejectQueryCallbacks = [];

    /**
     * @var array<callable(QueuedJob): bool>
     */
    private $rejectQueuedJobCallbacks = [];

    /**
     * @api
     *
     * @param  callable(CacheEvent): bool  $callback
     */
    public function rejectCacheEvents(callable $callback): void
    {
        $this->rejectCacheEventCallbacks[] = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Mail): bool  $callback
     */
    public function rejectMail(callable $callback): void
    {
        $this->rejectMailCallbacks[] = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Notification): bool  $callback
     */
    public function rejectNotifications(callable $callback): void
    {
        $this->rejectNotificationCallbacks[] = $callback;
    }

    /**
     * @api
     *
     * @param  callable(OutgoingRequest): bool  $callback
     */
    public function rejectOutgoingRequests(callable $callback): void
    {
        $this->rejectOutgoingRequestCallbacks[] = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Query): bool  $callback
     */
    public function rejectQueries(callable $callback): void
    {
        $this->rejectQueryCallbacks[] = $callback;
    }

    /**
     * @api
     *
     * @param  callable(QueuedJob): bool  $callback
     */
    public function rejectQueuedJobs(callable $callback): void
    {
        $this->rejectQueuedJobCallbacks[] = $callback;
    }
}
