<?php

namespace Laravel\Nightwatch\Concerns;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\Request;

trait RedactsRecords
{
    /**
     * @var ?callable(CacheEvent): bool
     */
    private $redactCacheEventCallback = null;

    /**
     * @var ?callable(Command): bool
     */
    private $redactCommandCallback = null;

    /**
     * @var ?callable(Mail): bool
     */
    private $redactMailCallback = null;

    /**
     * @var ?callable(OutgoingRequest): bool
     */
    private $redactOutgoingRequestCallback = null;

    /**
     * @var ?callable(Query): bool
     */
    private $redactQueryCallback = null;

    /**
     * @var ?callable(Request): bool
     */
    private $redactRequestCallback = null;

    /**
     * @api
     *
     * @param  callable(CacheEvent): bool  $callback
     */
    public function redactCacheEvents(callable $callback): void
    {
        $this->redactCacheEventCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Command): bool  $callback
     */
    public function redactCommands(callable $callback): void
    {
        $this->redactCommandCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Mail): bool  $callback
     */
    public function redactMail(callable $callback): void
    {
        $this->redactMailCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(OutgoingRequest): bool  $callback
     */
    public function redactOutgoingRequests(callable $callback): void
    {
        $this->redactOutgoingRequestCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Query): bool  $callback
     */
    public function redactQueries(callable $callback): void
    {
        $this->redactQueryCallback = $callback;
    }

    /**
     * @api
     *
     * @param  callable(Request): bool  $callback
     */
    public function redactRequests(callable $callback): void
    {
        $this->redactRequestCallback = $callback;
    }
}
