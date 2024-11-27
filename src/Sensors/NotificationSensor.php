<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Types\Str;
use Laravel\Nightwatch\UserProvider;

use function hash;
use function str_contains;

/**
 * @internal
 */
final class NotificationSensor
{
    public function __construct(
        private ExecutionState $executionState,
        private UserProvider $user,
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

        $this->executionState->notifications_sent++;

        $this->executionState->records->write(new Notification(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('md5', $class),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            channel: $event->channel,
            class: $class,
            duration: 0, // TODO
            failed: false, // TODO
        ));
    }
}
