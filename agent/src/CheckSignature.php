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
            $this->signatureCheck(
                loop: $this->loop,
                basePath: $this->basePath,
                expectedSignature: $this->expectedSignature,
                onShutdownInitiated: $this->onShutdownInitiated,
                onShutdown: $this->onShutdown
            );
        });
    }

    /**
     * @param  (Closure(int $shuttingDownIn): void)  $onShutdownInitiated
     * @param  (Closure(): void)  $onShutdown
     */
    private function signatureCheck(LoopInterface $loop, string $basePath, string $expectedSignature, Closure $onShutdownInitiated, Closure $onShutdown): void
    {
        $signature = @file_get_contents($basePath.'/signature.txt');

        if ($signature !== $expectedSignature) {
            $loop->cancelTimer($this->signatureCheckTimer);

            $shuttingDownIn = 5;
            $onShutdownInitiated($shuttingDownIn);
            $shuttingDownIn--;

            $shutdownTimer = $loop->addPeriodicTimer(60, static function () use ($loop, &$shutdownTimer, $onShutdownInitiated, $onShutdown, &$shuttingDownIn) {
                if ($shuttingDownIn <= 0) {
                    /** @var TimerInterface $shutdownTimer */
                    $loop->cancelTimer($shutdownTimer);
                    $onShutdown();
                } else {
                    $onShutdownInitiated($shuttingDownIn);
                    $shuttingDownIn--;
                }
            });
        }
    }
}
