<?php

namespace Laravel\Nightwatch\State;

use Closure;
use Illuminate\Foundation\Application;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;
use Laravel\Nightwatch\UserProvider;

use function call_user_func;
use function memory_get_peak_usage;

/**
 * @internal
 */
final class RequestState
{
    public int $v = 1;

    public string $id;

    public string $source = 'request';

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
        public UserProvider $user,
        public ExecutionStage $stage = ExecutionStage::Bootstrap,
        public array $stageDurations = [
            ExecutionStage::Bootstrap->value => 0,
            ExecutionStage::BeforeMiddleware->value => 0,
            ExecutionStage::Action->value => 0,
            ExecutionStage::Render->value => 0,
            ExecutionStage::AfterMiddleware->value => 0,
            ExecutionStage::Sending->value => 0,
            ExecutionStage::Terminating->value => 0,
            ExecutionStage::End->value => 0,
        ],
        public int $exceptions = 0,
        public int $queries = 0,
        public int $lazyLoads = 0,
        public int $jobsQueued = 0,
        public int $logs = 0,
        public int $mail = 0,
        public int $notifications = 0,
        public int $outgoingRequests = 0,
        public int $filesRead = 0,
        public int $filesWritten = 0,
        public int $cacheEvents = 0,
        public int $logs = 0,
        public int $hydratedModels = 0,
        public RecordsBuffer $records = new RecordsBuffer,
        public string $phpVersion = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
        public string $laravelVersion = Application::VERSION,
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
}
