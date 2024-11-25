<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;

/**
 * @internal
 */
final class Notification
{
    public int $v = 1;

    public string $t = 'notification';

    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string $user,
        // --- //
        public string $channel,
        public string $class,
        public int $duration,
        public bool $failed,
    ) {
        //
    }
}
