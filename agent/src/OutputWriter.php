<?php

namespace Laravel\NightwatchAgent;

use React\Stream\WritableResourceStream;

use function Nightwatch\fwrite_all;

class OutputWriter
{
    private WritableResourceStream $loopStream;

    /**
     * @param  resource  $stream
     */
    public function __construct(
        private Loop $loop,
        private $stream,
    ) {
        $this->loopStream = new WritableResourceStream($stream);
    }

    public function write(string $message): void
    {
        if ($this->loop->running()) {
            $this->loopStream->write($message);
        } else {
            fwrite_all($this->stream, $message);
        }
    }
}
