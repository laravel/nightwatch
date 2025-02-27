<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\EventLoop\Loop;
use RuntimeException;

use function call_user_func;
use function file_get_contents;
use function trim;

class Signature
{
    private string $signature;

    /**
     * @param  (Closure(string $before, string $after): mixed)  $onChange
     */
    public function __construct(
        private string $path,
        private int $verificationIntervalInSeconds,
        private Closure $onChange,
    ) {
        //
    }

    public function capture(): void
    {
        $signature = file_get_contents($this->path);

        if ($signature === false) {
            throw new RuntimeException('Unable to read the signature file');
        }

        if (trim($signature) === '') {
            throw new RuntimeException('Signature file is empty');
        }

        $this->signature = $signature;

        Loop::addPeriodicTimer($this->verificationIntervalInSeconds, function () {
            // TODO must not throw an exception
            $this->verify(...);
        });
    }

    private function verify(): void
    {
        $newSignature = file_get_contents($this->path);

        if ($newSignature === false) {
            throw new RuntimeException('Unable to verify the signature file');
        }

        if ($this->signature !== $newSignature) {
            call_user_func($this->onChange, $this->signature, $newSignature);
        }
    }
}
