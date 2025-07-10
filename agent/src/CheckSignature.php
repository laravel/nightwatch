<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

use function file_get_contents;

class CheckSignature
{
    private TimerInterface $checkTimer;

    private TimerInterface $shutdownTimer;

    /**
     * @param  LoopInterface  $loop
     * @param  (Closure(int $shuttingDownIn): void)  $onShutdownInitiated
     * @param  (Closure(): void)  $onShutdown
     */
    public function __construct(
        private $loop,
        private string $signaturePath,
        private string $expectedSignature,
        private int $shutdownDelayInMinutes,
        private Closure $onShutdownInitiated,
        private Closure $onShutdown,
    ) {
        //
    }

    public function start(): void
    {
        $this->checkTimer = $this->loop->addPeriodicTimer(60, $this->check(...));
    }

    private function check(): void
    {
        $signature = @file_get_contents($this->signaturePath);

        if ($signature === $this->expectedSignature) {
            return;
        }

        $this->scheduledShutdown();
    }

    private function scheduledShutdown(): void
    {
        $this->loop->cancelTimer($this->checkTimer);

        $shuttingDownIn = $this->shutdownDelayInMinutes;
        ($this->onShutdownInitiated)($shuttingDownIn);

        $this->shutdownTimer = $this->loop->addPeriodicTimer(60, function () use (&$shuttingDownIn) {
            $shuttingDownIn--;
            if ($shuttingDownIn <= 0) {
                $this->loop->cancelTimer($this->shutdownTimer);
                ($this->onShutdown)();
            } else {
                ($this->onShutdownInitiated)($shuttingDownIn);
            }
        });
    }
}
