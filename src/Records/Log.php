<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Log
{
    public int $v = 1;

    public string $t = 'log';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  LazyValue<string>  $execution_preview
     * @param  LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public LazyValue $execution_id,
        public LazyValue $execution_preview,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- //
        public string $level,
        public string $message,
        public string $context,
        public string $extra,
    ) {
        $this->level = Str::tinyText($this->level);
        $this->message = Str::text($this->message);
        $this->context = Str::mediumText($this->context);
        $this->extra = Str::mediumText($this->extra);
    }
}
