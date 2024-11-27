<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;

/**
 * @internal
 */
final class Mail
{
    public int $v = 1;

    public string $t = 'mail';

    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_id,
        public string $execution_source,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string $user,
        // --- //
        public string $mailer,
        public string $class,
        public string $subject,
        public int $to,
        public int $cc,
        public int $bcc,
        public int $attachments,
        public int $duration,
        public bool $failed,
    ) {
        //
    }
}
