<?php

namespace Laravel\NightwatchAgent;

use function fflush;
use function fwrite;
use function Nightwatch\fwrite_all;
use function substr;

class OutputWriter
{
    private string $buffer = '';

    /**
     * @param  resource  $stream
     */
    public function __construct(
        private Loop $loop,
        private $stream,
        private bool $async,
    ) {
        //
    }

    public function write(string $message): void
    {
        if ($this->async && $this->loop->running()) {
            $this->writeAsync($message);
        } else {
            $this->writeSync($message);
        }
    }

    private function writeAsync(string $message): void
    {
        if ($this->buffer === '') {
            $this->loop->addWriteStream($this->stream, $this->flush(...));
        }

        $this->buffer .= $message;
    }

    private function flush(): void
    {
        $written = @fwrite($this->stream, substr($this->buffer, 0, 4096));

        $this->buffer = $written ? substr($this->buffer, $written) : '';

        if ($this->buffer === '') {
            $this->loop->removeWriteStream($this->stream);
        }
    }

    private function writeSync(string $message): void
    {
        if ($this->buffer !== '') {
            $this->loop->removeWriteStream($this->stream);

            $message = $this->buffer.$message;
            $this->buffer = '';
        }

        fwrite_all($this->stream, $message);
        fflush($this->stream);
    }
}
