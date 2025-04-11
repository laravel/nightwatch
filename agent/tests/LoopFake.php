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
     * @var list<array{runAt: float, scheduledBy: string, interval: float, callback: ?callable, instance: ?TimerInterface }>
     */
    public array $pendingTimers = [];

    /**
     * @var list<array{runAt: float, scheduledBy: string, interval: float }>
     */
    public array $canceledTimers = [];

    /**
     * @var list<array{interval: float, runAt: float, scheduledBy: string}>
     */
    public array $timersRun = [];

    private float $now;

    public function __construct(
        private float $runForSeconds = 0,
    ) {
        $this->now = microtime(true);
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
                'runAt' => ($this->now - $pendingTimer['runAt']) * -1,
                'scheduledBy' => $pendingTimer['scheduledBy'],
                'interval' => $pendingTimer['interval'],
            ];

            unset($this->pendingTimers[$index]);

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
        $startedAt = $this->now;
        $stopRunningAt = $this->now + $this->runForSeconds;

        while (count($this->pendingTimers)) {
            if ($this->now >= $stopRunningAt) {
                $this->pendingTimers = array_map(fn ($pendingTimer) => [
                    'interval' => $pendingTimer['interval'],
                    'runAt' => $pendingTimer['runAt'] - $startedAt,
                    'scheduledBy' => $pendingTimer['scheduledBy'],
                    'callback' => null,
                ], $this->pendingTimers);

                return;
            }

            [
                'runAt' => $runAt,
                'scheduledBy' => $scheduledBy,
                'interval' => $interval,
                'callback' => $callback,
            ] = $this->pendingTimers[0];

            /** @var callable $callback */
            if ($this->now >= $runAt) {
                $callback();

                $this->timersRun[] = [
                    'interval' => $interval,
                    'runAt' => $this->now - $startedAt,
                    'scheduledBy' => $scheduledBy,
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

    private function sortPendingTimers()
    {
        usort($this->pendingTimers, function ($a, $b) {
            if ($a['runAt'] === $b['runAt']) {
                return 0;
            }

            return $a['runAt'] < $b['runAt'] ? -1 : 1;
        });
    }
}
