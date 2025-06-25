<?php

namespace Laravel\Nightwatch\Records;

final class Mail
{
    public function __construct(
        public readonly string $mailer,
        public readonly string $class,
        public string $subject,
        public readonly int $to,
        public readonly int $cc,
        public readonly int $bcc,
        public readonly int $attachments,
        public readonly int $duration,
        public readonly bool $failed,
    ) {
        //
    }
}
