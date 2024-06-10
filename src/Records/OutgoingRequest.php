<?php

namespace Laravel\Nightwatch\Records;

class OutgoingRequest
{
    /**
     * @param  non-empty-string  $timestamp
     * @param  non-empty-string  $group
     * @param  non-empty-string  $trace_id
     * @param  'job'|'request'  $execution_context
     * @param  non-empty-string  $execution_id
     * @param 'GET'|'HEAD'|'POST'|'PUT'|'DELETE'|'CONNECT'|'OPTIONS'|'TRACE'|'PATCH' $method
     * @param non-empty-string $url
     * @param  non-negative-int  $duration
     * @param  non-negative-int $request_size_kilobytes
     * @param  non-negative-int $response_size_kilobytes
     * @param  non-empty-string  $status_code,
     */
    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public string $user,
        public string $method,
        public string $url,
        public int $duration,
        public int $request_size_kilobytes,
        public int $response_size_kilobytes,
        public string $status_code,
    ) {
        //
    }
}
