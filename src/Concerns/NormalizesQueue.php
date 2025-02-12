<?php

namespace Laravel\Nightwatch\Concerns;

use function preg_quote;
use function preg_replace;

/**
 * @internal
 */
trait NormalizesQueue
{
    /**
     * TODO memory leak?
     *
     * @var array<string, array<string, string>>
     */
    private array $normalizedQueues = [];

    private function normalizeQueue(string $connection, string $queue): string
    {
        $key = "{$connection}:{$queue}";

        if (isset($this->normalizedQueues[$connection][$queue])) {
            return $this->normalizedQueues[$connection][$queue];
        }

        $config = $this->connectionConfig[$connection] ?? [];

        if (($config['driver'] ?? '') !== 'sqs') {
            return $this->normalizedQueues[$connection][$key] = $queue;
        }

        if ($config['prefix'] ?? false) {
            $prefix = preg_quote($config['prefix'], '#');

            $queue = preg_replace("#^{$prefix}/#", '', $queue) ?? $queue;
        }

        if ($config['suffix'] ?? false) {
            $suffix = preg_quote($config['suffix'], '#');

            $queue = preg_replace("#{$suffix}$#", '', $queue) ?? $queue;
        }

        return $this->normalizedQueues[$connection][$key] = $queue;
    }
}
