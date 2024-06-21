<?php

namespace Laravel\Nightwatch;

use Throwable;

class Location
{
    public function fromException(Throwable $e): string
    {
        if (! $this->isVendorFile($e->getFile())) {
            return $this->formatLocation($e->getFile(), $e->getLine());
        }

        if ($location = $this->fromTrace($e->getTrace())) {
            return $location;
        }

        return $this->formatLocation($e->getFile(), $e->getLine());
    }

    /**
     * @param  list<array{ file?: string, line?: int }>
     */
    public function fromTrace(array $trace): ?string
    {
        $file = $line = null;

        foreach ($trace as $frame) {
            if (array_key_exists('file', $frame) && ! $this->isVendorFile($frame['file'])) {
                return $this->formatLocation($frame['file'], $frame['line'] ?? null);
            }
        }

        return null;
    }

    private function isVendorFile(string $file): bool
    {
        return str_starts_with($file, base_path('vendor')) ||
            $file === base_path('artisan') ||
            $file === public_path('index.php');
    }

    private function formatLocation(string $file, int|null $line): string
    {
        return substr($file, strlen(base_path()) + 1).($line ? ':'.$line : '');
    }
}
