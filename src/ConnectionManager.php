<?php

namespace Laravel\Package;

use Closure;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use Throwable;
use WeakMap;

class ConnectionManager
{
    /**
     * @var (Closure(ConnectionInterface): void)|null
     */
    private Closure|null $onTimeoutCallback;

    /**
     * @var (Closure(ConnectionInterface, Throwable): void)|null
     */
    private Closure|null $onErrorCallback;

    private TimerInterface|null $flushAfterDelayTimer = null;

    /**
     * @param WeakMap<ConnectionInterface, array{ 0: list<string>, 1: TimerInterface }> $connections
     */
    public function __construct(
        private LoopInterface $loop,
        private Buffer $buffer,
        private Ingest $ingest,
        private float $timeout,
        private WeakMap $connections = new WeakMap,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addPeriodicTimer($this->timeout, function () use ($connection) {
            $this->cleanup($connection);

            $this->onTimeoutCallback?->__invoke($connection);
        });

        $this->connections[$connection] = [[], $timeoutTimer];

        $connection->on('data', function (string $data) use ($connection): void {
            $this->connections[$connection][0][] = $data;
        });

        $connection->on('end', function () use ($connection): void {
            $data = implode('', $this->connections[$connection][0]);

            $this->cleanup($connection);

            $this->buffer->write($data);

            if ($this->buffer->wantsFlushing()) {
                if ($this->flushAfterDelayTimer) {
                    $this->loop->cancelTimer($this->flushAfterDelayTimer);
                    $this->flushAfterDelayTimer = null;
                }

                $this->ingest->flush($this->buffer);
            } else {
                $this->flushAfterDelayTimer ??= $this->loop->addTimer(10, function (): void { // TODO: migrate to config
                    $this->flushAfterDelayTimer = null;

                    $this->ingest->flush($this->buffer);
                });
            }

        $connection->on('close', function () use ($connection): void {
            $this->cleanup($connection);
        });

        $connection->on('error', function () use ($connection): void {
            $this->cleanup($connection);

            $this->onErrorCallback?->__invoke($connection);
        });
    }

    /**
     * @param  (callable(ConnectionInterface): void)  $callback
     */
    public function onTimeout(callable $callback): void
    {
        $this->onTimeoutCallback = Closure::fromCallable($callback);
    }

    /**
     * @param  (callable(ConnectionInterface, Throwable): void)  $callback
     */
    public function onError(callable $callback): void
    {
        $this->onErrorCallback = Closure::fromCallable($callback);
    }

    private function cleanup(ConnectionInterface $connection): void
    {
        if (isset($this->connections[$connection])) {
            $this->loop->cancelTimer($this->connections[$connection][1]);
            $connection->close();
            unset($this->connections[$connection]);
        }
    }

    /**
     * @return list<ConnectionInterface>
     */
    public function connections(): array
    {
        $connections = [];

        foreach ($this->connections as $connection => $_) {
            $connections[] = $connection;
        }

        return $connections;
    }
}
