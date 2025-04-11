<?php

namespace Tests;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\TimerInterface;
use RuntimeException;

use function array_map;
use function array_shift;
use function count;
use function debug_backtrace;
use function microtime;
use function str_starts_with;
use function usort;

class LoopFake implements LoopInterface
{
    /**
     * @var list<array{runAt: float, scheduledAt: float, scheduledBy: string, interval: float, callback: ?callable, instance: ?TimerInterface }>
     */
    public array $pendingTimers = [];

    /**
     * @var list<array{canceledAt: float, scheduledAt: float, scheduledBy: string, interval: float }>
     */
    public array $canceledTimers = [];

    /**
     * @var list<array{interval: float, runAt: float, scheduledAt: float, scheduledBy: string}>
     */
    public array $timersRun = [];

    private float $now;

    private float $startedAt;

    public function __construct(
        private float $runForSeconds = 0,
    ) {
        $this->startedAt = $this->now = microtime(true);
    }

    /**
     * @param  callable  $listener
     * @param  resource  $stream
     */
    public function addReadStream($stream, $listener): void
    {
        //
    }

    /**
     * @param  callable  $listener
     * @param  resource  $stream
     */
    public function addWriteStream($stream, $listener): void
    {
        //
    }

    /**
     * @param  resource  $stream
     */
    public function removeReadStream($stream): void
    {
        //
    }

    /**
     * @param  resource  $stream
     */
    public function removeWriteStream($stream): void
    {
        //
    }

    /**
     * @param  int|float  $interval
     * @param  callable  $callback
     */
    public function addTimer($interval, $callback): TimerInterface
    {
        $timer = new Timer($interval, $callback, periodic: false);

        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $class = $frame['class'] ?? '';

        if (str_starts_with($class, 'P\\Tests\\Feature')) {
            $scheduledBy = $class;
        } else {
            $scheduledBy = "{$class}::{$frame['function']}";
        }

        $this->pendingTimers[] = [
            'runAt' => $this->now + $interval,
            'scheduledAt' => $this->now - $this->startedAt,
            'scheduledBy' => $scheduledBy,
            'interval' => $interval,
            'callback' => $callback,
            'instance' => $timer,
        ];

        $this->sortPendingTimers();

        return $timer;
    }

    /**
     * @param  int|float  $interval
     * @param  callable  $callback
     */
    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        throw new RuntimeException('Not yet implemented');
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        foreach ($this->pendingTimers as $index => $pendingTimer) {
            if ($pendingTimer['instance'] !== $timer) {
                continue;
            }

            $this->canceledTimers[] = [
                'canceledAt' => $this->now - $this->startedAt,
                'scheduledBy' => $pendingTimer['scheduledBy'],
                'scheduledAt' => $pendingTimer['scheduledAt'],
                'interval' => $pendingTimer['interval'],
            ];

            unset($this->pendingTimers[$index]); // @phpstan-ignore assign.propertyType

            $this->sortPendingTimers();

            return;
        }
    }

    /**
     * @param  callable  $listener
     */
    public function futureTick($listener)
    {
        throw new RuntimeException(__FUNCTION__);
    }

    /**
     * @param  int  $signal
     * @param  callable  $listener
     */
    public function addSignal($signal, $listener): void
    {
        throw new RuntimeException(__FUNCTION__);
    }

    /**
     * @param  int  $signal
     * @param  callable  $listener
     */
    public function removeSignal($signal, $listener): void
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function run(): void
    {
        $stopRunningAt = $this->now + $this->runForSeconds;

        while (count($this->pendingTimers)) {
            if ($this->now >= $stopRunningAt) {
                $this->pendingTimers = array_map(fn ($pendingTimer) => [
                    'interval' => $pendingTimer['interval'],
                    'runAt' => $pendingTimer['runAt'] - $this->startedAt,
                    'scheduledAt' => $pendingTimer['scheduledAt'],
                    'scheduledBy' => $pendingTimer['scheduledBy'],
                    'callback' => null,
                    'instance' => null,
                ], $this->pendingTimers);

                return;
            }

            [
                'runAt' => $runAt,
                'scheduledBy' => $scheduledBy,
                'scheduledAt' => $scheduledAt,
                'interval' => $interval,
                'callback' => $callback,
            ] = $this->pendingTimers[0];

            /** @var callable $callback */
            if ($this->now >= $runAt) {
                $callback();

                $this->timersRun[] = [
                    'interval' => $interval,
                    'runAt' => $this->now - $this->startedAt,
                    'scheduledBy' => $scheduledBy,
                    'scheduledAt' => $scheduledAt,
                ];

                array_shift($this->pendingTimers);

                continue;
            }

            $this->now = $runAt;
        }
    }

    public function stop(): void
    {
        throw new RuntimeException(__FUNCTION__);
    }

    private function sortPendingTimers(): void
    {
        usort($this->pendingTimers, function ($a, $b) {
            if ($a['runAt'] === $b['runAt']) {
                return 0;
            }

            return $a['runAt'] < $b['runAt'] ? -1 : 1;
        });
    }
}
