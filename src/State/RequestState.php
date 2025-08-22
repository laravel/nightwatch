<?php

namespace Laravel\Nightwatch\State;

use Closure;
use Illuminate\Foundation\Application;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
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

    /**
     * @var 'request'
     */
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
        public string $id,
        public string $deploy,
        public string $server,
        public float $currentExecutionStageStartedAtMicrotime,
        public UserProvider $user,
        public ?string $routeAction = null,
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
        public int $logs = 0,
        public int $queries = 0,
        public int $lazyLoads = 0,
        public int $jobsQueued = 0,
        public int $mail = 0,
        public int $notifications = 0,
        public int $outgoingRequests = 0,
        public int $filesRead = 0,
        public int $filesWritten = 0,
        public int $cacheEvents = 0,
        public int $hydratedModels = 0,
        public string $phpVersion = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
        public string $laravelVersion = Application::VERSION,
        public string $executionPreview = '',
        public string $exceptionPreview = '',
    ) {
        $this->deploy = Str::tinyText($this->deploy);
        $this->server = Str::tinyText($this->server);
    }

    /**
     * @return LazyValue<string>
     */
    public function id(): LazyValue
    {
        return new LazyValue(fn () => $this->id);
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return LazyValue<string>
     */
    public function executionPreview(): LazyValue
    {
        return new LazyValue(fn () => $this->executionPreview);
    }

    public function peakMemory(): int
    {
        if ($this->peakMemoryResolver !== null) {
            return call_user_func($this->peakMemoryResolver);
        }

        return memory_get_peak_usage(true);
    }

    public function flush(): void
    {
        $this->routeAction = null;
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
        $this->executionPreview = '';
        $this->exceptionPreview = '';
        $this->user->flush();
        foreach ($this->stageDurations as $key => $value) {
            $this->stageDurations[$key] = 0;
        }
    }
}
