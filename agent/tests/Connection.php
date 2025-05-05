<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;
use RuntimeException;

class Connection extends EventEmitter implements ConnectionInterface
{
    public function __construct(
        public string $payload = '',
        public bool $closed = false,
    ) {
        //
    }

    public static function closed(
        string $payload = '',
    ): self {
        return new self($payload, closed: true);
    }

    public function getRemoteAddress()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function getLocalAddress()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function isReadable()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function pause()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function resume()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    /**
     * @param  array<mixed>  $options
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function close()
    {
        $this->closed = true;
    }

    public function isWritable()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function write($data)
    {
        if (! $this->closed) {
            $this->payload .= (string) $data; // @phpstan-ignore cast.string

            return true;
        }

        return false;
    }

    public function end($data = null)
    {
        if ($this->closed) {
            return;
        }

        if ($data) {
            $this->write($data);
        }

        $this->close();
    }
}
