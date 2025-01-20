<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;

/**
 * @internal
 */
final class Notification
{
    public int $v = 1;

    public string $t = 'notification';

    /**
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_source,
        public string $trace_id,
        public string $execution_source,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- //
        public string $channel,
        public string $class,
        public int $duration,
        public bool $failed,
    ) {
        //
    }
}
