<?php

namespace Laravel\Nightwatch\State;

use Closure;
use Illuminate\Console\Application as Artisan;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;

use function call_user_func;
use function memory_get_peak_usage;

/**
 * @internal
 */
final class CommandState
{
    public int $v = 1;

    public string $id;

    public string $source = 'command';

    /**
     * @var (Closure(): int)|null
     */
    public ?Closure $peakMemoryResolver = null;

    /**
     * @param  array<value-of<ExecutionStage>, int>  $stageDurations
     */
    public function __construct(
        public float $timestamp,
        public string $trace,
        public string $deploy,
        public string $server,
        public float $currentExecutionStageStartedAtMicrotime,
        public ExecutionStage $stage,
        public array $stageDurations,
        public int $exceptions,
        public int $logs,
        public int $queries,
        public int $lazyLoads,
        public int $jobsQueued,
        public int $mail,
        public int $notifications,
        public int $outgoingRequests,
        public int $filesRead,
        public int $filesWritten,
        public int $cacheEvents,
        public int $hydratedModels,
        public RecordsBuffer $records,
        public string $phpVersion,
        public string $laravelVersion,
        public ?Artisan $artisan,
        public ?string $name,
        private Clock $clock,
    ) {
        $this->deploy = Str::tinyText($this->deploy);
        $this->server = Str::tinyText($this->server);
        $this->id = $trace;
    }

    public function peakMemory(): int
    {
        if ($this->peakMemoryResolver !== null) {
            return call_user_func($this->peakMemoryResolver);
        }

        return memory_get_peak_usage(true);
    }

    public function prepareForNextJobAttempt(): void
    {
        $this->id = (string) Str::uuid();
        $this->timestamp = $this->clock->microtime();
        $this->exceptions = 0;
        $this->logs = 0;
        $this->queries = 0;
        $this->lazyLoads = 0;
        $this->jobsQueued = 0;
        $this->mail = 0;
        $this->notifications = 0;
        $this->outgoingRequests = 0;
        $this->filesRead = 0;
        $this->filesWritten = 0;
        $this->cacheEvents = 0;
        $this->hydratedModels = 0;
    }
}
