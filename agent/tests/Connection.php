<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;
use RuntimeException;

class Connection extends EventEmitter implements ConnectionInterface
{
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
        //
    }

    public function isWritable()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function write($data)
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function end($data = null)
    {
        throw new RuntimeException(__FUNCTION__);
    }
}
