<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Text;

final class Request
{
    public int $v = 1;

    public function __construct(
        public int $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $user,
        // --- //
        public string $method,
        public string $scheme,
        public string $url_user,
        public string $host,
        public string $port,
        public string $path,
        public string $query,
        public string $route_name,
        public array $route_methods,
        public string $route_domain,
        public string $route_path,
        public string $route_action,
        public string $ip,
        public int $duration,
        public string $status_code,
        // --- //
        public int $request_size_kilobytes,
        public ?int $response_size_kilobytes,
        public int $queries,
        public int $queries_duration,
        public int $lazy_loads,
        public int $lazy_loads_duration,
        public int $jobs_queued,
        public int $mail_queued,
        public int $mail_sent,
        public int $mail_duration,
        public int $notifications_queued,
        public int $notifications_sent,
        public int $notifications_duration,
        public int $outgoing_requests,
        public int $outgoing_requests_duration,
        public int $files_read,
        public int $files_read_duration,
        public int $files_written,
        public int $files_written_duration,
        public int $cache_hits,
        public int $cache_misses,
        public int $hydrated_models,
        public int $peak_memory_usage_kilobytes,
    ) {
        $this->path = Text::limit($this->path);
    }
}
