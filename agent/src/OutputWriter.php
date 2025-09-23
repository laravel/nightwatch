<?php

namespace Laravel\NightwatchAgent;

use React\Stream\WritableResourceStream;

use function fwrite;

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
            fwrite($this->stream, $message);
        }
    }
}
