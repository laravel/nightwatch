<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

use function array_map;

/**
 * @internal
 */
final class Request extends Record
{
    public int $v = 1;

    public string $t = 'request';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $user
     * @param  list<string>  $route_methods
     * @param  LazyValue<int>  $exceptions,
     * @param  LazyValue<int>  $logs,
     * @param  LazyValue<int>  $queries,
     * @param  LazyValue<int>  $lazy_loads,
     * @param  LazyValue<int>  $jobs_queued,
     * @param  LazyValue<int>  $mail,
     * @param  LazyValue<int>  $notifications,
     * @param  LazyValue<int>  $outgoing_requests,
     * @param  LazyValue<int>  $files_read,
     * @param  LazyValue<int>  $files_written,
     * @param  LazyValue<int>  $cache_events,
     * @param  LazyValue<int>  $hydrated_models,
     * @param  LazyValue<int>  $peak_memory_usage,
     * @param  LazyValue<string>  $exception_preview,
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string|LazyValue $user,
        // --- //
        public string $method,
        public string $url,
        public string $route_name,
        public array $route_methods,
        public string $route_domain,
        public string $route_path,
        public string $route_action,
        public string $ip,
        public int $duration,
        public int $status_code,
        public int $request_size,
        public int $response_size,
        public int $bootstrap,
        public int $before_middleware,
        public int $action,
        public int $render,
        public int $after_middleware,
        public int $sending,
        public int $terminating,
        public LazyValue $exceptions,
        public LazyValue $logs,
        public LazyValue $queries,
        public LazyValue $lazy_loads,
        public LazyValue $jobs_queued,
        public LazyValue $mail,
        public LazyValue $notifications,
        public LazyValue $outgoing_requests,
        public LazyValue $files_read,
        public LazyValue $files_written,
        public LazyValue $cache_events,
        public LazyValue $hydrated_models,
        public LazyValue $peak_memory_usage,
        public LazyValue $exception_preview,
    ) {
        $this->method = Str::tinyText($this->method);
        $this->url = Str::text($this->url);
        $this->route_name = Str::tinyText($this->route_name);
        $this->route_methods = array_map(static fn ($method) => Str::tinyText($method), $this->route_methods);
        $this->route_domain = Str::tinyText($this->route_domain);
        $this->route_path = Str::text($this->route_path);
        $this->route_action = Str::text($this->route_action);
        $this->exception_preview = Str::tinyText($this->exception_preview);
    }
}
