<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
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

    /**
     * @return array{0: Mail, 1: callable(): array<mixed>}
     */
    public function __invoke(MessageSending|MessageSent $event): ?array
    {
        if (isset($event->data['__laravel_notification'])) {
            return null;
        }

        $now = $this->clock->microtime();

        if ($event instanceof MessageSending) {
            $this->startTime = $now;

            return null;
        }

        $class = $event->data['__laravel_mailable'] ?? '';

        if ($this->startTime === null) {
            throw new RuntimeException("No start time found for [{$class}].");
        }

        return [
            $record = new Mail(
                mailer: $event->data['mailer'] ?? '',
                class: $class,
                subject: $event->message->getSubject() ?? '',
                to: count($event->message->getTo()),
                cc: count($event->message->getCc()),
                bcc: count($event->message->getBcc()),
                attachments: count($event->message->getAttachments()),
                duration: (int) round(($now - $this->startTime) * 1_000_000),
                failed: false, // TODO: The framework doesn't dispatch a failed event.
            ),
            function () use ($now, $record) {
                $this->executionState->mail++;

                return [
                    'v' => 1,
                    't' => 'mail',
                    'timestamp' => $now,
                    'deploy' => $this->executionState->deploy,
                    'server' => $this->executionState->server,
                    '_group' => hash('xxh128', $record->class),
                    'trace_id' => $this->executionState->trace,
                    'execution_source' => $this->executionState->source,
                    'execution_id' => $this->executionState->id(),
                    'execution_preview' => $this->executionState->executionPreview(),
                    'execution_stage' => $this->executionState->stage,
                    'user' => $this->executionState->user->id(),
                    // --- //
                    'mailer' => Str::tinyText($record->mailer),
                    'class' => Str::tinyText($record->class),
                    'subject' => Str::tinyText($record->subject),
                    'to' => $record->to,
                    'cc' => $record->cc,
                    'bcc' => $record->bcc,
                    'attachments' => $record->attachments,
                    'duration' => $record->duration,
                    'failed' => $record->failed,
                ];
            },
        ];
    }
}
