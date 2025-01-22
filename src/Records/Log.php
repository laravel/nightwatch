<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;

/**
 * @internal
 */
final class Log
{
    public int $v = 1;

    public string $t = 'log';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $execution_id
     * @param  LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public string|LazyValue $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- //
        public string $level,
        public string $message,
        public string $context,
        public string $extra,
    ) {
        //
    }
}
