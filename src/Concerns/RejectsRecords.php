<?php

namespace Laravel\Nightwatch\Concerns;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;

/**
 * @internal
 */
trait RejectsRecords
{
    /**
     * @var list<callable(CacheEvent): bool>
     */
    private array $rejectCacheEventCallbacks = [];

    private bool $captureDefaultVendorCacheKeys = false;

    /**
     * @var list<string>
     */
    private array $rejectCacheKeys = [];

    /**
     * @var list<callable(Mail): bool>
     */
    private array $rejectMailCallbacks = [];

    /**
     * @var list<callable(Notification): bool>
     */
    private array $rejectNotificationCallbacks = [];

    /**
     * @var list<callable(OutgoingRequest): bool>
     */
    private array $rejectOutgoingRequestCallbacks = [];

    /**
     * @var list<callable(Query): bool>
     */
    private array $rejectQueryCallbacks = [];

    /**
     * @var list<callable(QueuedJob): bool>
     */
    private array $rejectQueuedJobCallbacks = [];

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
     * @param  list<string>  $keys
     */
    public function rejectCacheKeys(array $keys): void
    {
        $this->rejectCacheKeys = [
            ...$this->rejectCacheKeys,
            ...$keys,
        ];
    }

    /**
     * @api
     */
    public function captureDefaultVendorCacheKeys(bool $capture = true): void
    {
        $this->captureDefaultVendorCacheKeys = $capture;
    }

    /**
     * @api
     *
     * @return list<string>
     */
    public static function defaultVendorCacheKeys(): array
    {
        return [
            '/(^laravel_vapor_job_attemp(t?)s:)/', // Laravel Vapor keys...
            '/^illuminate:(?!cache:flexible:created:)/', // Laravel keys...
            '/^framework\/schedule/', // Scheduler keys...
            '/^laravel:pulse:/', // Pulse keys...
            '/^laravel:reverb:/', // Reverb keys...
            '/^nova/', // Nova keys...
            '/^telescope:/', // Telescope keys...
        ];
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
