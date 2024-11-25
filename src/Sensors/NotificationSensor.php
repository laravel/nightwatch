<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\UserProvider;

use function count;

/**
 * @internal
 */
final class NotificationSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(NotificationSent $event): void
    {
        $now = $this->clock->microtime();

        $this->executionState->notifications_sent++;

        $this->recordsBuffer->write(new Notification(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: '',
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            channel: $event->channel,
            class: $event->notification::class,
            duration: 0, // TODO
            failed: false, // TODO
        ));
    }
}
