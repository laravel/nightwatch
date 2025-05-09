<?php

namespace Laravel\Nightwatch\Contracts;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\Records\Log;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\Records\User;

/**
 * @internal
 */
interface LocalIngest
{
    public function write(Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob|JobAttempt|Mail|Notification|Log|User|ScheduledTask $record): void;

    public function ping(): void;

    public function digest(): void;

    public function flush(): void;
}
