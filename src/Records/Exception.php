<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

final class Exception
{
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
        private readonly string $class,
        private readonly string $file,
        private readonly int $line,
        private readonly string $message,
        private readonly string $code,
        private readonly string $trace,
        private readonly bool $handled,
        private readonly string $phpVersion,
        private readonly string $laravelVersion,
    ) {
        //
    }

    /**
     * @internal
     */
    public function toBaseRecord(): Record
    {
        return new Record([
            'v' => 1,
            't' => 'exception',
            'timestamp' => $this->timestamp,
            'deploy' => $this->deploy,
            'server' => $this->server,
            '_group' => $this->_group,
            'trace_id' => $this->trace_id,
            'execution_source' => $this->execution_source,
            'execution_id' => $this->execution_id,
            'execution_preview' => $this->execution_preview,
            'execution_stage' => $this->execution_stage,
            'user' => $this->user,
            // --- //
            'class' => Str::tinyText($this->class),
            'file' => Str::tinyText($this->file),
            'line' => $this->line,
            'message' => Str::text($this->message),
            'code' => $this->code,
            'trace' => Str::mediumText($this->trace),
            'handled' => $this->handled,
            'php_version' => $this->php_version,
            'laravel_version' => $this->laravel_version,
        ]);
    }
}
