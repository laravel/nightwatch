<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Exception
{
    public int $v = 1;

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
        public string $class,
        public string $file,
        public int $line,
        public string $message,
        public int $code,
        public string $trace,
    ) {
        $this->class = Str::tinyText($this->class);
        $this->file = Str::tinyText($this->file);
        $this->message = Str::text($this->message);
        $this->trace = Str::mediumText($this->trace);
    }
}

