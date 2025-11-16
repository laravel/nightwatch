<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

use function clearstatcache;
use function file_get_contents;

class CheckSignature
{
    private TimerInterface $checkTimer;

    private TimerInterface $shutdownTimer;

    /**
     * @param  (Closure(string $signature): void)  $onCheckSignature
     * @param  (Closure(int $shuttingDownIn): void)  $onShutdownInitiated
     * @param  (Closure(): void)  $onShutdown
     */
    public function __construct(
        private LoopInterface $loop,
        private string $signaturePath,
        private string $expectedSignature,
        private int $shutdownDelayInMinutes,
        private Closure $onCheckSignature,
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
        clearstatcache(clear_realpath_cache: true, filename: $this->signaturePath);
        $signature = @file_get_contents($this->signaturePath) ?: '';

        ($this->onCheckSignature)($signature);

        if ($signature === $this->expectedSignature || $signature === '') {
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
