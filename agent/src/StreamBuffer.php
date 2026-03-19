<?php

namespace Laravel\NightwatchAgent;

use Clue\React\Zlib\Compressor;

use function strlen;
use function substr;

class StreamBuffer
{
    private string $buffer = '';

    private int $length = 0;

    private $compressor;

    public function __construct(
        private int $threshold,
    ) {
        $this->refreshCompressor();
    }

    public function write(Payload $payload): void
    {
        $input = substr($payload->pull(), 1, -1);

        if ($this->length === 0) {
            $this->length = strlen($input);
            $this->compressor->write($input);
        } else {
            $this->length = $this->length + 1 + strlen($input);
            $this->compressor->write(','.$input);
        }
    }

    public function reachedThreshold(): bool
    {
        return $this->length >= $this->threshold;
    }

    public function willExceedThresholdWith(Payload $payload): bool
    {
        // -2 to account for the removal of the `[` and `]` characters when
        // appending to the stream.
        return ($this->length + (strlen($payload->value) - 2)) > $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function pull(): string
    {
        $this->compressor->end(']}');

        $payload = $this->buffer;

        $this->flush();

        return $payload;
    }

    public function isNotEmpty(): bool
    {
        return $this->length > 0;
    }

    public function flush(): void
    {
        $this->buffer = '';
        $this->length = 0;
        $this->refreshCompressor();
    }

    private function refreshCompressor()
    {
        $this->compressor = new Compressor(ZLIB_ENCODING_GZIP, $_SERVER['NIGHTWATCH_AGENT_COMPRESSION_LEVEL'] ?? -1);
        $this->compressor->on('data', function (string $data) {
            $this->buffer .= $data;
        });
        $this->compressor->write('{"records":[');
    }
}
