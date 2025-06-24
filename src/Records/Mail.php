<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Mail extends Record
{
    public int $v = 1;

    public string $t = 'mail';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  LazyValue<string>  $execution_preview
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        private readonly float $timestamp,
        private readonly string $deploy,
        private readonly string $server,
        private readonly string $_group,
        private readonly string|LazyValue $trace_id,
        private readonly string $execution_source,
        private readonly LazyValue $execution_id,
        private readonly LazyValue $execution_preview,
        private readonly ExecutionStage $execution_stage,
        private readonly string|LazyValue $user,
        // --- //
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
        // $this->mailer = Str::tinyText($this->mailer);
        // $this->class = Str::tinyText($this->class);
        // $this->subject = Str::tinyText($this->subject);
    }
}
