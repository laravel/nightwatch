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
     * @param  LazyValue<string>  $user
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $trace_id,
        public string $execution_source,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- //
        public string $level,
        public string $message,
        public array $context,
        public array $extra,
    ) {
        //
    }
}
