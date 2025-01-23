<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;

/**
 * @internal
 */
final class Mail
{
    public int $v = 1;

    public string $t = 'mail';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public LazyValue $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
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
