<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use RuntimeException;

use function hash;
use function round;
use function str_contains;

/**
 * @internal
 */
final class NotificationSensor
{
    private ?float $startTime = null;

    private ?int $duration = null;

    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(NotificationSending|NotificationSent $event): void
    {
        $now = $this->clock->microtime();

        if ($event instanceof NotificationSending) {
            $this->startTime = $now;
            $this->duration = null;

            return;
        }

        if ($this->startTime === null) {
            throw new RuntimeException('No start time found for ['.$event->notifiable::class.'].'); // @phpstan-ignore classConstant.nonObject
        }

        if (str_contains($event->notification::class, "@anonymous\0")) {
            $class = Str::before($event->notification::class, "\0");
        } else {
            $class = $event->notification::class;
        }

        $this->duration = (int) round(($now - $this->startTime) * 1_000_000);
        $this->executionState->notifications++;

        $this->executionState->records->write(new Notification(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $class),
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id(),
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            channel: $event->channel,
            class: $class,
            duration: $this->duration,
            failed: false, // TODO: The framework doesn't dispatch the `NotificationFailed` event.
        ));
    }
}
