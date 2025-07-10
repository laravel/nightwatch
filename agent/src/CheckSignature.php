<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

use function file_get_contents;

class CheckSignature
{
    private TimerInterface $signatureCheckTimer;

    private TimerInterface $shutdownTimer;

    /**
     * @param  LoopInterface  $loop
     * @param  (Closure(int $shuttingDownIn): void)  $onShutdownInitiated
     * @param  (Closure(): void)  $onShutdown
     */
    public function __construct(
        private $loop,
        private string $basePath,
        private string $expectedSignature,
        private int $shutdownDelayInMinutes,
        private Closure $onShutdownInitiated,
        private Closure $onShutdown,
    ) {
        //
    }

    public function start(): void
    {
        $this->signatureCheckTimer = $this->loop->addPeriodicTimer(60, function () {
            $this->signatureCheck();
        });
    }

    private function signatureCheck(): void
    {
        $signature = @file_get_contents($this->basePath.'/signature.txt');

        if ($signature === $this->expectedSignature) {
            return;
        }

        $this->scheduledShutdown();
    }

    private function scheduledShutdown(): void
    {
        $this->loop->cancelTimer($this->signatureCheckTimer);

        $shuttingDownIn = $this->shutdownDelayInMinutes;
        ($this->onShutdownInitiated)($shuttingDownIn);

        $this->shutdownTimer = $this->loop->addPeriodicTimer(60, function () use (&$shuttingDownIn) {
            if ($shuttingDownIn <= 0) {
                $this->loop->cancelTimer($this->shutdownTimer);
                ($this->onShutdown)();
            } else {
                $shuttingDownIn--;
                ($this->onShutdownInitiated)($shuttingDownIn);
            }
        });
    }
}
