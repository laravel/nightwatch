<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;

use function hash;
use function str_contains;

/**
 * @internal
 */
final class NotificationSensor
{
    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(NotificationSent $event): void
    {
        $now = $this->clock->microtime();

        if (str_contains($event->notification::class, "@anonymous\0")) {
            $class = Str::before($event->notification::class, "\0");
        } else {
            $class = $event->notification::class;
        }

        $this->executionState->notifications++;

        $this->executionState->records->write(new Notification(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $class),
            trace_source: $this->executionState->traceSource,
            trace_id: $this->executionState->traceId,
            execution_source: $this->executionState->executionSource,
            execution_id: $this->executionState->executionId,
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            channel: $event->channel,
            class: $class,
            duration: 0, // TODO
            failed: false, // TODO
        ));
    }
}
