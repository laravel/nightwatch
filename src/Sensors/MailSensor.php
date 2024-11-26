<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Mail\Events\MessageSent;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\UserProvider;

use function count;
use function hash;

/**
 * @internal
 */
final class MailSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(MessageSent $event): void
    {
        $now = $this->clock->microtime();
        $class = $event->data['__laravel_mailable'] ?? '';

        $this->executionState->mail_sent++;

        $this->recordsBuffer->write(new Mail(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('md5', $class),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            mailer: $event->data['mailer'] ?? '',
            class: $class,
            subject: $event->message->getSubject() ?? '',
            to: count($event->message->getTo()),
            cc: count($event->message->getCc()),
            bcc: count($event->message->getBcc()),
            attachments: count($event->message->getAttachments()),
            duration: 0, // TODO
            failed: false, // TODO
        ));
    }
}
