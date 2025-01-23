<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Request
{
    public int $v = 1;

    public string $t = 'request';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $user
     * @param  list<string>  $route_methods
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
        public int $exceptions,
        public int $logs,
        public int $queries,
        public int $lazy_loads,
        public int $jobs_queued,
        public int $mail,
        public int $notifications,
        public int $outgoing_requests,
        public int $files_read,
        public int $files_written,
        public int $cache_events,
        public int $hydrated_models,
        public int $peak_memory_usage,
    ) {
        $this->url = Str::text($this->url);
        $this->route_name = Str::tinyText($this->route_name);
        $this->route_domain = Str::tinyText($this->route_domain);
        $this->route_path = Str::text($this->route_path);
        $this->route_action = Str::text($this->route_action);
    }
}
