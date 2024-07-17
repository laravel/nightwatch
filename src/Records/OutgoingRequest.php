<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class OutgoingRequest
{
    public int $v = 1;

    /**
     * TODO limit string length
     */
    public function __construct(
        public int $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public int $execution_offset,
        public string $user,
        // --- /
        public string $method,
        public string $scheme,
        public string $host,
        public string $port,
        public string $path,
        public string $route,
        public int $duration,
        public ?int $request_size_kilobytes,
        public ?int $response_size_kilobytes,
        public string $status_code,
    ) {
        //
    }
}
