<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\MediumText;
use Laravel\Nightwatch\Types\Text;
use Laravel\Nightwatch\Types\TinyText;

/**
 * @internal
 */
final class Exception
{
    public int $v = 1;

    public function __construct(
        public int $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public int $execution_offset,
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
