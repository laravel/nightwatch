<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

use function file_get_contents;

class CheckSignature
{
    private TimerInterface $signatureCheckTimer;

    /**
     * @param  LoopInterface  $loop
     * @param  (Closure(int $shuttingDownIn): void)  $onShutdownInitiated
     * @param  (Closure(): void)  $onShutdown
     */
    public function __construct(
        private $loop,
        private string $expectedSignature,
        private string $basePath,
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

        if ($signature !== $this->expectedSignature) {
            $this->loop->cancelTimer($this->signatureCheckTimer);

            $shuttingDownIn = 5;
            ($this->onShutdownInitiated)($shuttingDownIn);
            $shuttingDownIn--;

            $shutdownTimer = $this->loop->addPeriodicTimer(60, function () use (&$shutdownTimer, &$shuttingDownIn) {
                if ($shuttingDownIn <= 0) {
                    /** @var TimerInterface $shutdownTimer */
                    $this->loop->cancelTimer($shutdownTimer);
                    ($this->onShutdown)();
                } else {
                    ($this->onShutdownInitiated)($shuttingDownIn);
                    $shuttingDownIn--;
                }
            });
        }
    }
}
