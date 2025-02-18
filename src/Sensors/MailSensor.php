<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use RuntimeException;

use function count;
use function hash;
use function round;

/**
 * @internal
 */
final class MailSensor
{
    private ?float $startTime = null;

    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(MessageSending|MessageSent $event): void
    {
        if (isset($event->data['__laravel_notification'])) {
            return;
        }

        $now = $this->clock->microtime();

        if ($event instanceof MessageSending) {
            $this->startTime = $now;

            return;
        }

        $class = $event->data['__laravel_mailable'] ?? '';

        if ($this->startTime === null) {
            throw new RuntimeException("No start time found for [{$class}].");
        }

        $this->executionState->mail++;

        $this->executionState->records->write(new Mail(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $class),
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id(),
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            mailer: $event->data['mailer'] ?? '',
            class: $class,
            subject: $event->message->getSubject() ?? '',
            to: count($event->message->getTo()),
            cc: count($event->message->getCc()),
            bcc: count($event->message->getBcc()),
            attachments: count($event->message->getAttachments()),
            duration: (int) round(($now - $this->startTime) * 1_000_000),
            failed: false, // TODO: The framework doesn't dispatch a failed event.
        ));
    }
}
