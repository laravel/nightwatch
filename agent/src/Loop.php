<?php

namespace Laravel\NightwatchAgent;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class Loop implements LoopInterface
{
    private bool $running = false;

    public function __construct(
        private LoopInterface $loop,
    ) {
        //
    }

    public function running(): bool
    {
        return $this->running;
    }

    public function run(): void
    {
        $this->running = true;

        $this->loop->run();
    }

    public function stop(): void
    {
        $this->running = false;

        $this->loop->stop();
    }

    /**
     * @param  resource  $stream
     * @param  callable  $listener
     */
    public function addReadStream($stream, $listener): void
    {
        $this->loop->addReadStream($stream, $listener);
    }

    /**
     * @param  resource  $stream
     * @param  callable  $listener
     */
    public function addWriteStream($stream, $listener): void
    {
        $this->loop->addWriteStream($stream, $listener);
    }

    /*
     * @param resource $stream
     */
    public function removeReadStream($stream): void
    {
        $this->loop->removeReadStream($stream);
    }

    /**
     * @param  resource  $stream
     */
    public function removeWriteStream($stream): void
    {
        $this->loop->removeWriteStream($stream);
    }

    /**
     * @param  int|float  $interval
     * @param  callable  $callback
     */
    public function addTimer($interval, $callback): TimerInterface
    {
        return $this->loop->addTimer($interval, $callback);
    }

    /**
     * @param  int|float  $interval
     * @param  callable  $callback
     */
    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        return $this->loop->addPeriodicTimer($interval, $callback);
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        $this->loop->cancelTimer($timer);
    }

    /**
     * @param  callable  $listener
     */
    public function futureTick($listener): void
    {
        $this->loop->futureTick($listener);
    }

    /**
     * @param  int  $signal
     * @param  callable  $listener
     */
    public function addSignal($signal, $listener): void
    {
        $this->loop->addSignal($signal, $listener);
    }

    /**
     * @param  int  $signal
     * @param  callable  $listener
     */
    public function removeSignal($signal, $listener): void
    {
        $this->loop->removeSignal($signal, $listener);
    }
}
