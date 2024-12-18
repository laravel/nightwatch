<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;

/**
 * @internal
 */
final class Log
{
    public int $v = 1;

    public string $t = 'log';

    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $trace_id,
        public string $execution_source,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string $user,
        // --- //
        public string $level,
        public string $message,
        public array $context,
        public array $extra,
    ) {
        //
    }
}
