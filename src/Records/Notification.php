<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Notification
{
    public int $v = 1;

    public string $t = 'notification';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  LazyValue<string>  $execution_preview
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
        public LazyValue $execution_preview,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- //
        public string $channel,
        public string $class,
        public int $duration,
        public bool $failed,
    ) {
        $this->class = Str::tinyText($this->class);
        $this->channel = Str::tinyText($this->channel);
    }
}
