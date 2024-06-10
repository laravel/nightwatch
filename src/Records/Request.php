<?php

namespace Laravel\Nightwatch\Records;

class Request
{
    /**
     * @param  non-empty-string  $timestamp
     * @param  non-empty-string  $group
     * @param  non-empty-string  $trace_id
     * @param 'GET'|'HEAD'|'POST'|'PUT'|'DELETE'|'CONNECT'|'OPTIONS'|'TRACE'|'PATCH' $method
     * @param non-empty-string  $path
     * @param  non-empty-string  $ip
     * @param non-negative-int  $duration
     * @param  string  $status_code
     * @param  non-negative-int $request_size_kilobytes
     * @param  non-negative-int $request_size_kilobytes
     * @param  non-negative-int $response_size_kilobytes
     */
    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $method,
        public string $route,
        public string $path,
        public string $user,
        public string $ip,
        public int $duration,
        public string $status_code,
        public int $request_size_kilobytes,
        public int $response_size_kilobytes,
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
        //
    }
}
