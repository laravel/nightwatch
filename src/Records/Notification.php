<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Notification extends Record
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
        public readonly string $channel,
        public readonly string $class,
        public readonly int $duration,
        public readonly bool $failed,
    ) {
        // $this->class = Str::tinyText($this->class);
        // $this->channel = Str::tinyText($this->channel);
    }
}
