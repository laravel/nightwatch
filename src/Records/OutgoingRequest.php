<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class OutgoingRequest
{
    public int $v = 1;

    public string $t = 'outgoing-request';

    /**
     * TODO limit string length
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_id,
        public string $execution_source,
        public string $execution_id,
        public string $user,
        // --- /
        public string $host,
        public string $method,
        public string $url,
        public int $duration,
        public int $request_size,
        public int $response_size,
        public string $status_code,
    ) {
        //
    }
}
