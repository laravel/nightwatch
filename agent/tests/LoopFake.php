<?php

namespace Tests;

use PHPUnit\Framework\Assert;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer as ReactTimer;
use React\EventLoop\TimerInterface;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_shift;
use function array_values;
use function count;
use function debug_backtrace;
use function microtime;
use function str_starts_with;
use function usort;

class LoopFake implements LoopInterface
{
    /**
     * @var list<array{runAt: float, scheduledAt: float, scheduledBy: string, interval: float, callback: ?callable, instance: ?TimerInterface, periodic: bool }>
     */
    public array $pendingTimers = [];

    /**
     * @var list<array{canceledAt: float, scheduledAt: float, scheduledBy: string, interval: float }>
     */
    public array $canceledTimers = [];

    /**
     * @var list<array{interval: float, runAt: float, scheduledAt: float, scheduledBy: string, periodic: bool }>
     */
    public array $timersRun = [];

    public bool $stopped = false;

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

        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $class = $frame['class'] ?? '';

        if (str_starts_with($class, 'P\\Tests\\Feature')) {
            $scheduledBy = $class;
        } elseif ($class === '') {
            $scheduledBy = 'Agent';
        } else {
            $scheduledBy = "{$class}::{$frame['function']}";
        }

        return $this->timer($interval, $callback, $scheduledBy, periodic: false);
    }

    /**
     * @param  int|float  $interval
     * @param  callable  $callback
     * @param  string|null  $calledBy
     */
    public function addPeriodicTimer($interval, $callback, $calledBy = null): TimerInterface
    {

        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $class = $frame['class'] ?? '';

        if ($calledBy !== null) {
            $scheduledBy = $calledBy;
        } elseif ($class === '') {
            $scheduledBy = 'Agent';
        } elseif (str_starts_with($class, 'P\\Tests\\Feature')) {
            $scheduledBy = $class;
        } else {
            $scheduledBy = "{$class}::{$frame['function']}";
        }

        return $this->timer($interval, $callback, $scheduledBy, periodic: true);
    }

    public function timer(int|float $interval, callable $callback, string $scheduledBy, bool $periodic): TimerInterface
    {
        $timer = new ReactTimer($interval, $callback, periodic: $periodic);

        $this->pendingTimers[] = [
            'runAt' => $this->now + $interval,
            'scheduledAt' => $this->now - $this->startedAt,
            'scheduledBy' => $scheduledBy,
            'interval' => $interval,
            'callback' => $callback,
            'instance' => $timer,
            'periodic' => $periodic,
        ];

        $this->sortPendingTimers();

        return $timer;
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

        while (! $this->stopped && count($this->pendingTimers)) {
            if ($this->now >= $stopRunningAt) {
                $this->pendingTimers = array_map(fn ($pendingTimer) => [
                    'interval' => $pendingTimer['interval'],
                    'runAt' => $pendingTimer['runAt'] - $this->startedAt,
                    'scheduledAt' => $pendingTimer['scheduledAt'],
                    'scheduledBy' => $pendingTimer['scheduledBy'],
                    'callback' => null,
                    'instance' => null,
                    'periodic' => $pendingTimer['periodic'],
                ], $this->pendingTimers);

                return;
            }

            [
                'runAt' => $runAt,
                'scheduledBy' => $scheduledBy,
                'scheduledAt' => $scheduledAt,
                'interval' => $interval,
                'callback' => $callback,
                'instance' => $timer,
                'periodic' => $periodic,
            ] = $this->pendingTimers[0];

            /** @var callable $callback */
            if ($this->now >= $runAt) {
                $callback();

                $this->timersRun[] = [
                    'interval' => $interval,
                    'runAt' => $this->now - $this->startedAt,
                    'scheduledBy' => $scheduledBy,
                    'scheduledAt' => $scheduledAt,
                    'periodic' => $periodic,
                ];

                if ($periodic) {
                    $this->pendingTimers[0]['runAt'] = $this->now + $interval;
                    $this->sortPendingTimers();
                } else {
                    array_shift($this->pendingTimers);
                }

                continue;
            }

            $this->now = $runAt;
        }
    }

    public function stop(): void
    {
        $this->stopped = true;
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

    /**
     * @param  list<Timer>  $timers
     */
    public function assertPending(array $timers): self
    {
        $actual = array_values(array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            runAt: $timer['runAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
        ),
            array_filter($this->pendingTimers, fn ($timer) => $timer['periodic'] === false)
        ));

        Assert::assertEquals($timers, $actual);

        return $this;
    }

    /**
     * @param  list<Timer>  $timers
     */
    public function assertPendingWithPeriodic(array $timers): self
    {
        $actual = array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            runAt: $timer['runAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
            periodic: $timer['periodic'],
        ), $this->pendingTimers);

        Assert::assertEquals($timers, $actual);

        return $this;
    }

    /**
     * @param  list<Timer>  $timers
     */
    public function assertRun(array $timers): self
    {
        $actual = array_values(array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            runAt: $timer['runAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
        ),
            array_filter($this->timersRun, fn ($timer) => $timer['periodic'] === false)
        ));

        Assert::assertEquals($timers, $actual);

        return $this;
    }

    /**
     * @param  list<Timer>  $timers
     */
    public function assertRunWithPeriodic(array $timers): self
    {
        $actual = array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            runAt: $timer['runAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
            periodic: $timer['periodic'],
        ), $this->timersRun);

        Assert::assertEquals($timers, $actual);

        return $this;
    }

    /**
     * @param  list<Timer>  $timers
     */
    public function assertCanceled(array $timers): self
    {
        Assert::assertEquals($timers, array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            canceledAt: $timer['canceledAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
        ), $this->canceledTimers));

        return $this;
    }
}
