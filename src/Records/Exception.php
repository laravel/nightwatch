<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\MediumText;
use Laravel\Nightwatch\Text;
use Laravel\Nightwatch\TinyText;

final class Exception
{
    /**
     * @param  non-empty-string  $timestamp
     * @param  non-empty-string  $group
     * @param  non-empty-string  $trace_id
     * @param  'job'|'request'  $execution_context
     * @param  non-empty-string  $execution_id
     * @param  class-string  $class
     * @param  non-empty-string  $file
     * @param  non-negative-int  $line
     * @param  non-empty-string  $trace
     */
    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public string $user,
        // --- //
        public string $class,
        public string $file,
        public int $line,
        public string $message,
        public int $code,
        public string $trace,
    ) {
        $this->class = TinyText::limit($this->class);
        $this->file = TinyText::limit($this->file);
        $this->message = Text::limit($this->message);
        $this->trace = MediumText::limit($this->trace);
    }
}
